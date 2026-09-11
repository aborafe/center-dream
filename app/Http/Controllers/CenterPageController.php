<?php

namespace App\Http\Controllers;

use App\Actions\AddStudentSubject;
use App\Actions\ApplyEnrollmentDiscount;
use App\Actions\CreateCenterUser;
use App\Actions\CreateEnrollmentWithPayment;
use App\Actions\CreateSubject;
use App\Actions\CreateTeacher;
use App\Actions\RecordEnrollmentPayment;
use App\Actions\RecordTeacherPayout;
use App\Http\Requests\ReceiptPreviewRequest;
use App\Http\Requests\StoreAcademicYearRequest;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\StoreDiscountRequest;
use App\Http\Requests\StoreGradeRequest;
use App\Http\Requests\StoreStudentSubjectRequest;
use App\Http\Requests\StoreSubjectRequest;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\StoreTeacherPayoutRequest;
use App\Http\Requests\StoreTeacherRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\AcademicYear;
use App\Models\CenterSetting;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherPayout;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CenterPageController extends Controller
{
    public function dashboard(): View
    {
        $payments = Payment::query()
            ->with(['student', 'enrollment.subject'])
            ->whereDate('paid_at', today())
            ->latest('paid_at')
            ->get();
        $enrollments = Enrollment::query()->with(['payments', 'subject'])->get();
        $due = $enrollments->sum(fn (Enrollment $enrollment): float => max(0, (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount')));
        $subjectCounts = Subject::query()->withCount('enrollments')->where('is_active', true)->orderByDesc('enrollments_count')->get();
        $maxCount = max(1, (int) $subjectCounts->max('enrollments_count'));

        return view('screens.dashboard', [
            'todayCollections' => (float) $payments->sum('amount'),
            'todayPaymentCount' => $payments->count(),
            'studentDebt' => $due,
            'studentsWithDebt' => $enrollments->filter(fn (Enrollment $enrollment): bool => (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount') > 0)->pluck('student_id')->unique()->count(),
            'todayDiscounts' => (float) Discount::query()->whereDate('created_at', today())->sum('amount'),
            'todayDiscountCount' => Discount::query()->whereDate('created_at', today())->count(),
            'activeSubjectCount' => $subjectCounts->count(),
            'recentPayments' => $payments->take(5),
            'subjectSubscriptions' => $subjectCounts->map(fn (Subject $subject): array => [$subject->name, $subject->enrollments_count.' طلاب', (int) round($subject->enrollments_count / $maxCount * 100), 'blue']),
        ]);
    }

    public function students(Request $request): View
    {
        $searchQuery = trim((string) $request->query('q', ''));
        $subjectId = $request->integer('subject_id') ?: null;
        $students = Student::query()
            ->with(['enrollments.subject', 'enrollments.payments'])
            ->when($searchQuery !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', "%{$searchQuery}%")
                ->orWhere('phone', 'like', "%{$searchQuery}%")))
            ->when($subjectId, fn ($query) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('subject_id', $subjectId)))
            ->latest()
            ->get()
            ->map(function (Student $student): array {
                $paid = (float) $student->enrollments->sum(fn ($enrollment): float => (float) $enrollment->payments->sum('amount'));
                $due = (float) $student->enrollments->sum(fn ($enrollment): float => (float) $enrollment->fee - (float) $enrollment->discount_amount) - $paid;
                $latestPayment = $student->enrollments
                    ->flatMap(fn ($enrollment) => $enrollment->payments)
                    ->sortByDesc('paid_at')
                    ->first();

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'subjects' => $student->enrollments->pluck('subject.name')->filter()->join('، ') ?: 'لا توجد مواد',
                    'paid' => number_format($paid, 2).' ج.م',
                    'last_payment' => $latestPayment?->paid_at?->translatedFormat('j F Y') ?? 'لم تُسجل دفعة',
                    'balance' => number_format($due, 2).' ج.م',
                    'status' => $due > 0 ? 'partial' : 'paid',
                    'status_label' => $due > 0 ? 'له رصيد' : 'منتظم',
                ];
            });

        return view('screens.students', compact('students', 'searchQuery', 'subjectId'));
    }

    public function subscription(): View
    {
        return view('screens.subscription', [
            'subjects' => Subject::query()->with(['teacher', 'grade'])->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function lookupStudent(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:24']]);
        $phone = $this->normalizeEgyptianPhone((string) $request->query('phone'));
        $student = Student::query()->with(['grade', 'academicYear', 'enrollments.subject'])->where('phone', $phone)->first();

        if (! $student) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'student' => [
                'name' => $student->name,
                'grade' => $student->grade->name,
                'academic_year' => $student->academicYear->name,
                'subjects' => $student->enrollments->pluck('subject.name')->filter()->values(),
            ],
        ]);
    }

    public function studentProfile(Student $student): View
    {
        $student->load(['grade', 'academicYear', 'enrollments.subject.teacher', 'enrollments.payments', 'enrollments.discounts', 'enrollments.refunds.processor']);
        $subscriptions = $student->enrollments->map(function ($enrollment): array {
            $paid = (float) $enrollment->payments->sum('amount');
            $remaining = (float) $enrollment->fee - (float) $enrollment->discount_amount - $paid;

            return ['id' => $enrollment->id, 'subject' => $enrollment->subject->name, 'teacher' => $enrollment->subject->teacher?->name ?? 'غير محدد', 'fee' => number_format((float) $enrollment->fee, 2).' ج.م', 'discount' => number_format((float) $enrollment->discount_amount, 2).' ج.م', 'paid' => number_format($paid, 2).' ج.م', 'remaining' => number_format(max(0, $remaining), 2).' ج.م', 'date' => $enrollment->created_at->translatedFormat('j F Y'), 'status' => $enrollment->cancelled_at ? 'ملغى' : ($remaining > 0 ? 'جزئي' : 'مكتمل'), 'raw_remaining' => max(0, $remaining), 'raw_paid' => $paid];
        })->all();
        $balance = $student->enrollments->sum(fn ($enrollment): float => (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount'));
        $availableSubjects = Subject::query()
            ->with('teacher')
            ->where('academic_year_id', $student->academic_year_id)
            ->where('grade_id', $student->grade_id)
            ->where('is_active', true)
            ->whereNotIn('id', $student->enrollments->pluck('subject_id'))
            ->orderBy('name')
            ->get();

        return view('screens.student', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'phone' => $student->phone,
                'grade' => $student->grade->name,
                'academic_year' => $student->academicYear->name,
                'guardian_name' => $student->guardian_name,
                'guardian_phone' => $student->guardian_phone,
                'note' => $student->note,
                'balance' => number_format($balance, 2).' ج.م',
                'subscriptions' => $subscriptions,
            ],
            'availableSubjects' => $availableSubjects,
            'enrollments' => $student->enrollments,
            'refunds' => $student->enrollments->flatMap->refunds->sortByDesc('refunded_at'),
            'completedPayment' => request()->integer('payment') ? Payment::query()->with(['student', 'enrollment.subject'])->where('student_id', $student->id)->find(request()->integer('payment')) : null,
        ]);

    }

    public function storeSubscription(StoreSubscriptionRequest $request, CreateEnrollmentWithPayment $createEnrollment): RedirectResponse
    {
        $enrollment = $createEnrollment->handle($request->validated(), $request->user())->firstOrFail();

        return redirect()
            ->route('students.show', $enrollment->student->id)
            ->with('status', 'تم حفظ الاشتراك والتحصيل بنجاح.');
    }

    public function inventory(): View
    {
        return view('screens.inventory', ['materials' => $this->materialSummary()]);
    }

    public function academics(): View
    {
        return view('screens.academics', [
            'materials' => $this->materialSummary(),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => Grade::query()->orderBy('sort_order')->get(),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('name')->get(),
            'managedSubjects' => Subject::query()->with(['teacher', 'academicYear', 'grade'])->orderBy('name')->get(),
        ]);
    }

    public function storeSubject(StoreSubjectRequest $request, CreateSubject $createSubject): RedirectResponse
    {
        $createSubject->handle($request->validated());

        return redirect()->route('academics.index')->with('status', 'تمت إضافة المادة وربطها بالصف والمدرس.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'fee' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $subject->update([
            'name' => $data['name'],
            'teacher_id' => $data['teacher_id'] ?? null,
            'fee' => $data['fee'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('academics.index')->with('status', 'تم تحديث المادة وحالتها بنجاح.');
    }

    public function storeAcademicYear(StoreAcademicYearRequest $request): RedirectResponse
    {
        if ($request->boolean('is_active')) {
            AcademicYear::query()->update(['is_active' => false]);
        }

        AcademicYear::query()->create([...$request->validated(), 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('academics.index')->with('status', 'تمت إضافة السنة الدراسية.');
    }

    public function storeGrade(StoreGradeRequest $request): RedirectResponse
    {
        Grade::query()->create($request->validated());

        return redirect()->route('academics.index')->with('status', 'تمت إضافة الصف الدراسي.');
    }

    public function teachers(): View
    {
        $teachers = Teacher::query()->with(['subjects.enrollments.payments', 'payouts'])->get()->map(function (Teacher $teacher): array {
            $enrollments = $teacher->subjects->flatMap->enrollments;
            $collections = (float) $enrollments->sum(fn (Enrollment $enrollment): float => (float) $enrollment->payments->sum('amount'));
            $payouts = (float) $teacher->payouts->sum('amount');

            return ['id' => $teacher->id, 'name' => $teacher->name, 'phone' => $teacher->phone, 'is_active' => $teacher->is_active, 'subjects' => $teacher->subjects->pluck('name')->join('، ') ?: 'لا توجد مواد', 'students' => $enrollments->pluck('student_id')->unique()->count(), 'collections' => number_format($collections, 2).' ج.م', 'wallet' => number_format($collections - $payouts, 2).' ج.م'];
        });

        return view('screens.teachers', compact('teachers'));
    }

    public function storeTeacher(StoreTeacherRequest $request, CreateTeacher $createTeacher): RedirectResponse
    {
        $createTeacher->handle($request->validated());

        return redirect()->route('teachers.index')->with('status', 'تمت إضافة المدرس بنجاح.');
    }

    public function updateTeacher(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:teachers,phone,'.$teacher->id],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $teacher->update([...$data, 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('teachers.index')->with('status', 'تم تحديث بيانات المدرس وحالته.');
    }

    public function teacherProfile(Teacher $teacher): View
    {
        $teacher->load(['subjects.enrollments.payments', 'payouts.payer']);
        $subjects = $teacher->subjects->map(function (Subject $subject): array {
            $paid = (float) $subject->enrollments->sum(fn (Enrollment $enrollment): float => (float) $enrollment->payments->sum('amount'));
            $due = (float) $subject->enrollments->sum(fn (Enrollment $enrollment): float => max(0, (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount')));

            return ['name' => $subject->name, 'students' => $subject->enrollments->pluck('student_id')->unique()->count(), 'paid' => $paid, 'due' => $due];
        });

        return view('screens.teacher', ['teacher' => $teacher, 'subjects' => $subjects, 'collections' => $subjects->sum('paid'), 'due' => $subjects->sum('due'), 'payoutTotal' => (float) $teacher->payouts->sum('amount')]);
    }

    public function users(): View
    {
        $users = User::query()->with(['roles.permissions', 'permissions'])->get()->map(function (User $user): array {
            $scope = $user->hasRole('admin') ? 'كامل الصلاحيات' : ($user->permissions->concat($user->roles->flatMap->permissions)->unique('id')->pluck('name')->join('، ') ?: 'لا توجد صلاحيات');

            return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'job_title' => $user->job_title, 'role' => $user->roles->pluck('name')->join('، ') ?: 'بلا دور', 'scope' => $scope, 'is_active' => $user->is_active];
        });

        return view('screens.users', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('name')->get(),
        ]);
    }

    public function storeUser(StoreUserRequest $request, CreateCenterUser $createUser): RedirectResponse
    {
        $createUser->handle($request->validated());

        return redirect()->route('users.index')->with('status', 'تم إنشاء المستخدم وتحديد صلاحياته.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($user->is($request->user()) && ! $request->boolean('is_active')) {
            return back()->withErrors(['is_active' => 'لا يمكنك إيقاف حسابك أثناء تسجيل الدخول به.']);
        }

        $user->update([...$data, 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('users.index')->with('status', 'تم تحديث المستخدم وحالة دخوله.');
    }

    public function reports(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'secretary_id' => ['nullable', 'integer', 'exists:users,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'type' => ['nullable', 'in:all,collection,payout,refund'],
        ]);
        $from = Carbon::parse($filters['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now())->endOfDay();
        $type = $filters['type'] ?? 'all';

        $payments = Payment::query()
            ->with(['student', 'receiver', 'enrollment.subject.teacher'])
            ->whereBetween('paid_at', [$from, $to])
            ->when(isset($filters['secretary_id']), fn ($query) => $query->where('received_by', $filters['secretary_id']))
            ->when(isset($filters['teacher_id']), fn ($query) => $query->whereHas('enrollment.subject', fn ($subject) => $subject->where('teacher_id', $filters['teacher_id'])))
            ->get();
        $payouts = TeacherPayout::query()
            ->with(['teacher', 'payer'])
            ->whereBetween('paid_at', [$from, $to])
            ->when(isset($filters['secretary_id']), fn ($query) => $query->where('paid_by', $filters['secretary_id']))
            ->when(isset($filters['teacher_id']), fn ($query) => $query->where('teacher_id', $filters['teacher_id']))
            ->get();
        $refunds = Refund::query()
            ->with(['student', 'enrollment.subject', 'processor'])
            ->whereBetween('refunded_at', [$from, $to])
            ->when(isset($filters['secretary_id']), fn ($query) => $query->where('refunded_by', $filters['secretary_id']))
            ->when(isset($filters['teacher_id']), fn ($query) => $query->whereHas('enrollment.subject', fn ($subject) => $subject->where('teacher_id', $filters['teacher_id'])))
            ->get();
        $enrollments = Enrollment::query()
            ->with('payments')
            ->when(isset($filters['teacher_id']), fn ($query) => $query->whereHas('subject', fn ($subject) => $subject->where('teacher_id', $filters['teacher_id'])))
            ->get();
        $dueByEnrollment = $enrollments->mapWithKeys(fn (Enrollment $enrollment): array => [$enrollment->id => max(0, (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount'))]);
        $subjectTotals = $payments->groupBy(fn (Payment $payment): string => $payment->enrollment->subject->name)->map(fn ($subjectPayments): float => (float) $subjectPayments->sum('amount'));
        $maxSubjectTotal = max(1, (float) $subjectTotals->max());
        $auditRows = collect()
            ->when($type !== 'payout', fn (Collection $rows) => $rows->push(...$payments->map(fn (Payment $payment): array => [
                'occurred_at' => $payment->paid_at,
                'type' => 'تحصيل طالب',
                'person' => $payment->student->name,
                'detail' => $payment->enrollment->subject->name,
                'executor' => $payment->receiver->name,
                'amount' => (float) $payment->amount,
                'direction' => 'in',
            ])))
            ->when($type !== 'collection', fn (Collection $rows) => $rows->push(...$payouts->map(fn (TeacherPayout $payout): array => [
                'occurred_at' => $payout->paid_at,
                'type' => 'صرف مدرس',
                'person' => $payout->teacher->name,
                'detail' => $payout->note ?: 'صرف مستحقات',
                'executor' => $payout->payer->name,
                'amount' => (float) $payout->amount,
                'direction' => 'out',
            ])))
            ->when($type === 'all' || $type === 'refund', fn (Collection $rows) => $rows->push(...$refunds->map(fn (Refund $refund): array => [
                'occurred_at' => $refund->refunded_at,
                'type' => 'رد مبلغ طالب',
                'person' => $refund->student->name,
                'detail' => $refund->enrollment->subject->name,
                'executor' => $refund->processor->name,
                'amount' => (float) $refund->amount,
                'direction' => 'out',
            ])))
            ->sortByDesc('occurred_at')
            ->values();

        return view('screens.reports', [
            'periodLabel' => $from->translatedFormat('j F Y').' — '.$to->translatedFormat('j F Y'),
            'filters' => [...$filters, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'type' => $type],
            'secretaries' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('name')->get(),
            'collectionTotal' => (float) $payments->sum('amount'),
            'teacherPayoutTotal' => (float) $payouts->sum('amount'),
            'refundTotal' => (float) $refunds->sum('amount'),
            'netCollections' => (float) $payments->sum('amount') - (float) $payouts->sum('amount') - (float) $refunds->sum('amount'),
            'completedEnrollments' => $dueByEnrollment->filter(fn (float $amount): bool => $amount === 0.0)->count(),
            'enrollmentCount' => $enrollments->count(),
            'dueTotal' => (float) $dueByEnrollment->sum(),
            'studentsWithDue' => $enrollments->filter(fn (Enrollment $enrollment): bool => $dueByEnrollment[$enrollment->id] > 0)->pluck('student_id')->unique()->count(),
            'reportRows' => $subjectTotals->map(fn (float $total, string $name): array => [$name, (int) round($total / $maxSubjectTotal * 100), 'blue', number_format($total, 2).' ج.م'])->values(),
            'auditRows' => $auditRows,
        ]);
    }

    public function collections(): View
    {
        return view('screens.collections', [
            'enrollments' => Enrollment::query()->with(['student', 'subject', 'payments'])->latest()->get()->filter(fn (Enrollment $enrollment): bool => (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount') > 0),
            'submissionToken' => (string) Str::uuid(),
            'completedPayment' => request()->integer('payment') ? Payment::query()->with(['student', 'enrollment.subject'])->find(request()->integer('payment')) : null,
            'receiptSize' => request()->query('size', 'A5') === 'A4' ? 'A4' : 'A5',
        ]);
    }

    public function storeCollection(StoreCollectionRequest $request, RecordEnrollmentPayment $recordPayment): RedirectResponse
    {
        $payment = $recordPayment->handle(
            $request->integer('enrollment_id'),
            (float) $request->input('amount'),
            $request->string('method')->toString(),
            $request->user(),
            $request->string('submission_token')->toString(),
        );

        if ($request->boolean('return_to_student')) {
            return redirect()->route('students.show', ['student' => $payment->student_id, 'payment' => $payment, 'size' => $request->string('size')->toString()]);
        }

        return redirect()->route('collections.create', ['payment' => $payment, 'size' => $request->string('size')->toString()]);
    }

    public function updateStudent(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'max:24'],
            'guardian_name' => ['nullable', 'string', 'max:120'], 'guardian_phone' => ['nullable', 'string', 'max:24'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['phone'] = $this->normalizeEgyptianPhone($data['phone']);
        $student->update($data);

        return back()->with('status', 'تم تحديث بيانات الطالب.');
    }

    public function storeStudentSubject(StoreStudentSubjectRequest $request, Student $student, AddStudentSubject $addStudentSubject): RedirectResponse
    {
        $addStudentSubject->handle($student, $request->validated(), $request->user());

        return back()->with('status', 'تمت إضافة المادة وتسجيل التحصيل المبدئي.');
    }

    public function destroyStudent(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('students.index')->with('status', 'تم حذف الطالب وكل اشتراكاته ومدفوعاته المرتبطة به.');
    }

    public function cancelEnrollment(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $data = $request->validate(['refund_amount' => ['required', 'numeric', 'min:0'], 'refund_method' => ['required', 'in:cash,transfer,wallet'], 'refund_note' => ['nullable', 'string', 'max:255']]);
        $paid = (float) $enrollment->payments()->sum('amount');
        if ((float) $data['refund_amount'] > $paid) {
            return back()->withErrors(['refund_amount' => 'الرد لا يمكن أن يتجاوز ما دُفع فعليًا.']);
        }
        DB::transaction(function () use ($data, $enrollment, $request): void {
            $enrollment->update(['cancelled_at' => now(), 'refund_amount' => $data['refund_amount'], 'refund_note' => $data['refund_note']]);
            if ((float) $data['refund_amount'] > 0) {
                Refund::query()->create(['student_id' => $enrollment->student_id, 'enrollment_id' => $enrollment->id, 'refunded_by' => $request->user()->id, 'amount' => $data['refund_amount'], 'method' => $data['refund_method'], 'note' => $data['refund_note'], 'refunded_at' => now()]);
            }
        });

        return back()->with('status', 'تم إلغاء الاشتراك وتسجيل مبلغ الرد.');
    }

    public function discounts(): View
    {
        $discounts = Discount::query()->with(['enrollment.student', 'enrollment.subject', 'approver'])->latest()->get();
        $enrollments = Enrollment::query()->with(['student', 'subject', 'payments'])->get();

        return view('screens.discounts', compact('discounts', 'enrollments'));
    }

    public function storeDiscount(StoreDiscountRequest $request, ApplyEnrollmentDiscount $applyDiscount): RedirectResponse
    {
        $applyDiscount->handle(
            $request->integer('enrollment_id'),
            $request->string('type')->toString(),
            (float) $request->input('value'),
            $request->string('reason')->toString(),
            $request->user(),
        );

        if ($request->boolean('return_to_student')) {
            $studentId = Enrollment::query()->findOrFail($request->integer('enrollment_id'))->student_id;

            return redirect()->route('students.show', $studentId)->with('status', 'تم اعتماد الخصم على حساب الطالب.');
        }

        return redirect()->route('discounts.index')->with('status', 'تم اعتماد الخصم.');
    }

    public function payouts(): View
    {
        $payouts = TeacherPayout::query()->with(['teacher', 'subject', 'payer'])->latest('paid_at')->get();
        $teachers = Teacher::query()->with(['subjects.enrollments.payments', 'payouts'])->where('is_active', true)->orderBy('name')->get()->map(function (Teacher $teacher): Teacher {
            $collected = (float) $teacher->subjects->flatMap->enrollments->sum(fn (Enrollment $enrollment): float => (float) $enrollment->payments->sum('amount'));
            $teacher->setAttribute('wallet_total', $collected - (float) $teacher->payouts->sum('amount'));
            $teacher->setAttribute('subject_count', $teacher->subjects->count());

            return $teacher;
        });

        return view('screens.payouts', compact('payouts', 'teachers'));
    }

    public function storePayout(StoreTeacherPayoutRequest $request, RecordTeacherPayout $recordPayout): RedirectResponse
    {
        $recordPayout->handle($request->validated(), $request->user());

        return redirect()->route('teacher-payouts.index')->with('status', 'تم تسجيل صرف المدرس بنجاح.');
    }

    public function profile(): View
    {
        return view('screens.profile', ['user' => request()->user()]);
    }

    public function settings(): View
    {
        return view('screens.settings', ['settings' => $this->centerSettings()]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['full_name', 'phone', 'email', 'job_title', 'password']);
        $request->user()->update([
            'name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'job_title' => $data['job_title'],
            ...($request->filled('password') ? ['password' => $data['password']] : []),
        ]);

        return back()->with('status', 'تم حفظ بيانات الملف الشخصي.');
    }

    public function updateSettings(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->centerSettings()->update([
            ...$request->safe()->only(['center_name', 'center_phone', 'address', 'currency']),
            'balance_alerts' => $request->boolean('balance_alerts'),
            'daily_summary' => $request->boolean('daily_summary'),
            'daily_report_copy' => $request->boolean('daily_report_copy'),
        ]);

        return redirect()->route('settings.edit')->with('status', 'تم حفظ إعدادات المركز.');
    }

    public function receipt(ReceiptPreviewRequest $request): View
    {
        $validated = $request->validated();

        return view('receipts.print', [
            'receipt' => [
                'student' => $validated['student'],
                'phone' => $validated['phone'],
                'subject' => $validated['subject'],
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'size' => $validated['size'] ?? 'A5',
                'number' => 'CD-'.now()->format('ymd-His'),
                'date' => now()->translatedFormat('j F Y'),
                'time' => now()->translatedFormat('h:i A'),
            ],
        ]);
    }

    public function storedReceipt(Payment $payment, Request $request): View
    {
        $payment->load(['student', 'enrollment.subject', 'receiver']);

        return view('receipts.print', [
            'receipt' => [
                'student' => $payment->student->name,
                'phone' => $payment->student->phone,
                'subject' => $payment->enrollment->subject->name,
                'amount' => number_format((float) $payment->amount, 2).' ج.م',
                'method' => ['cash' => 'نقدي', 'transfer' => 'تحويل', 'wallet' => 'محفظة'][$payment->method],
                'size' => $request->query('size', 'A5') === 'A4' ? 'A4' : 'A5',
                'number' => $payment->receipt_number,
                'date' => $payment->paid_at->translatedFormat('j F Y'),
                'time' => $payment->paid_at->translatedFormat('h:i A'),
                'receiver' => $payment->receiver->name,
            ],
        ]);
    }

    /**
     * Presentation data will be replaced by query results once the academic and financial models are connected.
     *
     * @return array<string, array<int, array<int, int|string>>>
     */
    private function workspaceData(): array
    {
        return [
            'recentSubscriptions' => [
                ['10:42 ص', 'سارة محمد', 'رياضيات — الصف الثالث الثانوي', '450 ج.م', 'تم الدفع', 'paid'],
                ['10:18 ص', 'عمر خالد', 'لغة إنجليزية — الصف الثاني', '350 ج.م', 'تم الدفع', 'paid'],
                ['09:55 ص', 'نور أحمد', 'فيزياء — الصف الثالث الثانوي', '500 ج.م', 'جزئي', 'partial'],
                ['09:32 ص', 'يوسف علي', 'كيمياء — الصف الثالث الثانوي', '450 ج.م', 'تم الدفع', 'paid'],
            ],
            'subjectSubscriptions' => [
                ['رياضيات', '8 طلاب', 94, 'blue'],
                ['لغة إنجليزية', '4 طلاب', 52, 'green'],
                ['فيزياء', '3 طلاب', 37, 'purple'],
                ['كيمياء', '3 طلاب', 37, 'orange'],
            ],
            'students' => [
                ['سارة محمد', 'رياضيات', '10 سبتمبر', '0 ج.م', 'منتظم', 'paid', '01095225454', 'sara-mohamed'],
                ['عمر خالد', 'لغة إنجليزية', '10 سبتمبر', '0 ج.م', 'منتظم', 'paid', '01000000000', 'omar-khaled'],
                ['نور أحمد', 'فيزياء', '10 سبتمبر', '200 ج.م', 'له رصيد', 'partial', '01110000000', null],
                ['يوسف علي', 'كيمياء', '9 سبتمبر', '0 ج.م', 'منتظم', 'paid', '01220000000', null],
                ['مريم سامح', 'رياضيات', '9 سبتمبر', '150 ج.م', 'له رصيد', 'partial', '01530000000', null],
                ['آدم حسن', 'لغة إنجليزية', '8 سبتمبر', '0 ج.م', 'منتظم', 'paid', '01040000000', null],
            ],
            'materials' => [
                ['رياضيات', 'أ. أحمد سامي', '8', '3,200 ج.م', 'blue'],
                ['لغة إنجليزية', 'أ. سارة نادر', '4', '1,400 ج.م', 'green'],
                ['فيزياء', 'أ. خالد طارق', '3', '1,500 ج.م', 'purple'],
                ['كيمياء', 'أ. منى أشرف', '3', '1,350 ج.م', 'orange'],
            ],
            'reportRows' => [
                ['رياضيات', 88, 'blue', '3,200 ج.م'],
                ['لغة إنجليزية', 61, 'green', '1,400 ج.م'],
                ['فيزياء', 48, 'purple', '1,500 ج.م'],
                ['كيمياء', 42, 'orange', '1,350 ج.م'],
            ],
            'teachers' => [
                ['أ. أحمد سامي', 'رياضيات', '18', '2,400 ج.م', 'مستحقات مفتوحة'],
                ['أ. سارة نادر', 'لغة إنجليزية', '14', '1,800 ج.م', 'مستحقات مفتوحة'],
                ['أ. خالد طارق', 'فيزياء', '12', '1,650 ج.م', 'مستحقات مفتوحة'],
            ],
            'users' => [
                ['محمود أحمد', 'مسؤول المركز', 'كامل الصلاحيات', 'نشط'],
                ['مريم عادل', 'سكرتير', 'الطلاب، التسجيل، التحصيل', 'نشط'],
                ['أحمد سامي', 'مدرس', 'مواده وطلابه فقط', 'نشط'],
            ],
        ];
    }

    /** @return Collection<int, array{0: string, 1: string, 2: int, 3: string, 4: string, 5: int}> */
    private function materialSummary(): Collection
    {
        return Subject::query()->with(['teacher', 'enrollments.payments'])->where('is_active', true)->get()->map(function (Subject $subject): array {
            $activeEnrollments = $subject->enrollments->whereNull('cancelled_at');
            $collected = (float) $activeEnrollments->sum(fn (Enrollment $enrollment): float => (float) $enrollment->payments->sum('amount'));
            $due = (float) $activeEnrollments->sum(fn (Enrollment $enrollment): float => max(0, (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount')));
            $eligibleStudents = Student::query()->where('academic_year_id', $subject->academic_year_id)->where('grade_id', $subject->grade_id)->count();

            return [
                $subject->name,
                $subject->teacher?->name ?? 'غير محدد',
                $activeEnrollments->count(),
                number_format($collected, 2).' ج.م',
                'blue',
                $subject->id,
                number_format($due, 2).' ج.م',
                $eligibleStudents,
                $activeEnrollments->flatMap->payments->count(),
                $subject->enrollments->filter(fn (Enrollment $enrollment): bool => $enrollment->cancelled_at !== null)->count(),
            ];
        });
    }

    private function centerSettings(): CenterSetting
    {
        return CenterSetting::query()->firstOrCreate([], [
            'center_name' => 'سنتر دريم',
            'center_phone' => '01000000000',
            'address' => 'القاهرة — مصر',
            'currency' => 'الجنيه المصري (ج.م)',
            'balance_alerts' => true,
            'daily_summary' => true,
            'daily_report_copy' => false,
        ]);
    }

    private function normalizeEgyptianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '20')) {
            $digits = substr($digits, 2);
        }

        return str_starts_with($digits, '0') ? $digits : '0'.$digits;
    }
}

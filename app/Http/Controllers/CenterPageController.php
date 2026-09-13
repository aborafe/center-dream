<?php

namespace App\Http\Controllers;

use App\Actions\AddStudentSubject;
use App\Actions\ApplyEnrollmentDiscount;
use App\Actions\CloseAcademicYear;
use App\Actions\CreateCenterUser;
use App\Actions\CreateEnrollmentWithPayment;
use App\Actions\CreateSubject;
use App\Actions\CreateTeacher;
use App\Actions\RecordDailyCashMovement;
use App\Actions\RecordEnrollmentPayment;
use App\Actions\RecordTeacherPayout;
use App\Http\Requests\ReceiptPreviewRequest;
use App\Http\Requests\StoreAcademicYearRequest;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\StoreDailyCashMovementRequest;
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
use App\Models\DailyCashMovement;
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
use App\Support\AcademicYearLedger;
use App\Support\DatePresenter;
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
        $academicYear = $this->activeAcademicYear();
        $payments = Payment::query()
            ->with(['student', 'enrollment.subject'])
            ->whereHas('enrollment.subject', fn ($query) => $query->where('academic_year_id', $academicYear?->id))
            ->whereDate('paid_at', today())
            ->latest('paid_at')
            ->get();
        $enrollments = Enrollment::query()->with(['payments', 'subject'])->whereHas('subject', fn ($query) => $query->where('academic_year_id', $academicYear?->id))->get();
        $activeEnrollments = $enrollments->whereNull('cancelled_at');
        $due = $activeEnrollments->sum(fn (Enrollment $enrollment): float => max(0, (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount')));
        $subjectCounts = Subject::query()
            ->with(['grade:id,name'])
            ->withCount([
                'enrollments as active_enrollments_count' => fn ($query) => $query->whereNull('cancelled_at'),
            ])
            ->where('academic_year_id', $academicYear?->id)
            ->where('is_active', true)
            ->orderByDesc('active_enrollments_count')
            ->get();
        $maxCount = max(1, (int) $subjectCounts->max('active_enrollments_count'));

        return view('screens.dashboard', [
            'todayCollections' => (float) $payments->sum('amount'),
            'todayPaymentCount' => $payments->count(),
            'studentDebt' => $due,
            'studentsWithDebt' => $activeEnrollments->filter(fn (Enrollment $enrollment): bool => (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount') > 0)->pluck('student_id')->unique()->count(),
            'todayDiscounts' => (float) Discount::query()->whereDate('created_at', today())->whereHas('enrollment.subject', fn ($query) => $query->where('academic_year_id', $academicYear?->id))->sum('amount'),
            'todayDiscountCount' => Discount::query()->whereDate('created_at', today())->whereHas('enrollment.subject', fn ($query) => $query->where('academic_year_id', $academicYear?->id))->count(),
            'activeSubjectCount' => $subjectCounts->count(),
            'recentPayments' => $payments->take(5),
            'subjectSubscriptions' => $subjectCounts->map(fn (Subject $subject): array => [
                $subject->name.' — '.($subject->grade?->name ?? 'صف غير محدد'),
                $subject->active_enrollments_count.' طلاب',
                (int) round($subject->active_enrollments_count / $maxCount * 100),
                'blue',
            ]),
            'academicYear' => $academicYear,
        ]);
    }

    public function students(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer', 'distinct', 'exists:subjects,id'],
            'account' => ['nullable', 'in:complete,due'],
            'balance' => ['nullable', 'in:due'],
        ]);
        $searchQuery = trim((string) ($filters['q'] ?? ''));
        $subjectId = $request->integer('subject_id') ?: null;
        $selectedSubjectIds = collect($filters['subjects'] ?? [])
            ->map(fn ($subjectId): int => (int) $subjectId)
            ->when($subjectId, fn (Collection $ids) => $ids->push($subjectId))
            ->unique()
            ->values();
        $accountFilter = $filters['account'] ?? (($filters['balance'] ?? null) === 'due' ? 'due' : null);
        $students = Student::query()
            ->with(['grade:id,name', 'enrollments.subject', 'enrollments.payments'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->when($searchQuery !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', "%{$searchQuery}%")
                ->orWhere('phone', 'like', "%{$searchQuery}%")))
            ->when($selectedSubjectIds->isNotEmpty(), fn ($query) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->whereIn('subject_id', $selectedSubjectIds)))
            ->latest()
            ->get()
            ->map(function (Student $student): array {
                $activeEnrollments = $student->enrollments->whereNull('cancelled_at');
                $paid = (float) $activeEnrollments->sum(fn ($enrollment): float => (float) $enrollment->payments->sum('amount'));
                $due = (float) $activeEnrollments->sum(fn ($enrollment): float => (float) $enrollment->fee - (float) $enrollment->discount_amount) - $paid;
                $latestPayment = $activeEnrollments
                    ->flatMap(fn ($enrollment) => $enrollment->payments)
                    ->sortByDesc('paid_at')
                    ->first();

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'grade' => $student->grade?->name ?? 'غير محدد',
                    'subjects' => $activeEnrollments->pluck('subject.name')->filter()->join('، ') ?: 'لا توجد مواد نشطة',
                    'paid' => number_format($paid, 2).' ج.م',
                    'raw_paid' => $paid,
                    'last_payment' => $latestPayment ? DatePresenter::date($latestPayment->paid_at) : 'لم تُسجل دفعة',
                    'last_payment_at' => $latestPayment?->paid_at,
                    'balance' => number_format($due, 2).' ج.م',
                    'raw_balance' => max(0, $due),
                    'status' => $due > 0 ? 'partial' : 'paid',
                    'status_label' => $due > 0 ? 'عليه رصيد' : 'مكتمل',
                ];
            })
            ->filter(fn (array $student): bool => $student['subjects'] !== 'لا توجد مواد نشطة')
            ->when($accountFilter === 'due', fn (Collection $rows) => $rows->filter(fn (array $student): bool => $student['raw_balance'] > 0))
            ->when($accountFilter === 'complete', fn (Collection $rows) => $rows->filter(fn (array $student): bool => $student['raw_balance'] <= 0))
            ->sortByDesc(fn (array $student) => $student['last_payment_at']?->getTimestamp() ?? 0)
            ->values();
        $studentTotals = [
            'count' => $students->count(),
            'paid' => (float) $students->sum('raw_paid'),
            'due' => (float) $students->sum('raw_balance'),
        ];
        $availableSubjects = Subject::query()
            ->with('grade:id,name')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'grade_id', 'name']);

        return view('screens.students', compact('students', 'searchQuery', 'subjectId', 'selectedSubjectIds', 'accountFilter', 'studentTotals', 'availableSubjects', 'academicYear'));
    }

    public function subscription(): View
    {
        $academicYear = $this->activeAcademicYear();

        return view('screens.subscription', [
            'subjects' => Subject::query()->with(['teacher', 'grade'])->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))->where('is_active', true)->orderBy('name')->get(),
            'grades' => Grade::query()
                ->when($academicYear, fn ($query) => $query->whereHas('subjects', fn ($subjects) => $subjects->where('academic_year_id', $academicYear->id)->where('is_active', true)))
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'academicYear' => $academicYear,
        ]);
    }

    public function lookupStudent(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:24']]);
        $phone = $this->normalizeEgyptianPhone((string) $request->query('phone'));
        $student = Student::query()->with(['grade', 'academicYear', 'enrollments.subject'])->where('phone', $phone)->when($this->activeAcademicYear(), fn ($query, AcademicYear $year) => $query->where('academic_year_id', $year->id))->first();

        if (! $student) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'grade_id' => $student->grade_id,
                'grade' => $student->grade->name,
                'academic_year' => $student->academicYear->name,
                'subjects' => $student->enrollments->pluck('subject.name')->filter()->values(),
                'profile_url' => route('students.show', $student),
            ],
        ]);
    }

    public function studentProfile(Student $student): View
    {
        $student->load(['grade', 'academicYear', 'enrollments.subject.teacher', 'enrollments.payments', 'enrollments.discounts', 'enrollments.refunds.processor']);
        $subscriptions = $student->enrollments->map(function ($enrollment): array {
            $paid = (float) $enrollment->payments->sum('amount');
            $remaining = (float) $enrollment->fee - (float) $enrollment->discount_amount - $paid;

            return ['id' => $enrollment->id, 'subject' => $enrollment->subject->name, 'teacher' => $enrollment->subject->teacher?->name ?? 'غير محدد', 'fee' => number_format((float) $enrollment->fee, 2).' ج.م', 'discount' => number_format((float) $enrollment->discount_amount, 2).' ج.م', 'paid' => number_format($paid, 2).' ج.م', 'remaining' => number_format(max(0, $remaining), 2).' ج.م', 'date' => DatePresenter::date($enrollment->created_at), 'status' => $enrollment->cancelled_at ? 'ملغى' : ($remaining > 0 ? 'جزئي' : 'مكتمل'), 'raw_remaining' => max(0, $remaining), 'raw_paid' => $paid];
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
        $academicYear = $this->activeAcademicYear();

        return view('screens.inventory', ['materials' => $this->materialSummary($academicYear?->id), 'academicYear' => $academicYear]);
    }

    public function academics(): View
    {
        $activeYear = $this->activeAcademicYear();

        return view('screens.academics', [
            'materials' => $this->materialSummary($activeYear?->id),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'grades' => Grade::query()->orderBy('sort_order')->get(),
            'teachers' => Teacher::query()->where('is_active', true)->orderBy('name')->get(),
            'managedSubjects' => Subject::query()->with(['teacher', 'academicYear', 'grade'])->when($activeYear, fn ($query) => $query->where('academic_year_id', $activeYear->id))->orderBy('name')->get(),
            'activeYear' => $activeYear,
        ]);
    }

    public function reportPreview(Request $request): View
    {
        $report = $this->reports($request)->getData();

        return view('reports.preview', [...$report, 'centerSettings' => $this->centerSettings()]);
    }

    public function reportWhatsApp(Request $request): View
    {
        $report = $this->reports($request)->getData();
        $recipients = collect()
            ->concat(User::query()->whereNotNull('phone')->where('phone', '!=', '')->orderBy('name')->get()->map(fn (User $user): array => [
                'type' => 'user', 'id' => $user->id, 'name' => $user->name, 'phone' => $user->phone, 'label' => 'مستخدم النظام',
            ]))
            ->concat(Teacher::query()->whereNotNull('phone')->where('phone', '!=', '')->orderBy('name')->get()->map(fn (Teacher $teacher): array => [
                'type' => 'teacher', 'id' => $teacher->id, 'name' => $teacher->name, 'phone' => $teacher->phone, 'label' => 'مدرس',
            ]));

        return view('screens.report-whatsapp', [...$report, 'recipients' => $recipients]);
    }

    public function redirectReportToWhatsApp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_type' => ['required', 'in:user,teacher,custom'],
            'recipient_id' => ['nullable', 'integer', 'required_unless:recipient_type,custom'],
            'recipient_phone' => ['nullable', 'string', 'max:30', 'required_if:recipient_type,custom'],
        ]);
        $phone = $data['recipient_phone'] ?? null;

        if ($data['recipient_type'] !== 'custom') {
            $recipient = $data['recipient_type'] === 'teacher'
                ? Teacher::query()->findOrFail($data['recipient_id'])
                : User::query()->findOrFail($data['recipient_id']);
            $phone = $recipient->phone;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return back()->withInput()->withErrors(['recipient_phone' => 'أدخل رقم واتساب صالحًا للمستلم.']);
        }

        if (str_starts_with($digits, '20')) {
            $digits = substr($digits, 2);
        }

        // A wa.me link can open a chat but cannot attach a file. Keep the chat empty so the
        // operator attaches the PDF created from the report preview instead of sending a summary.
        return redirect()->away('https://wa.me/20'.ltrim($digits, '0'));
    }

    public function storeSubject(StoreSubjectRequest $request, CreateSubject $createSubject): RedirectResponse
    {
        $createSubject->handle($request->validated());

        return redirect()->route('academics.index')->with('status', 'تمت إضافة المادة وربطها بالصف والمدرس.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $subject->loadMissing('academicYear');
        AcademicYearLedger::ensureOpen($subject->academicYear);
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

    public function destroySubject(Subject $subject): RedirectResponse
    {
        $subject->loadMissing('academicYear');
        AcademicYearLedger::ensureOpen($subject->academicYear);

        if ($subject->enrollments()->exists()) {
            return redirect()
                ->route('academics.index')
                ->withErrors(['subject' => 'لا يمكن حذف المادة لأنها مرتبطة باشتراكات طلاب. أوقفها من التعديل بدلًا من حذفها.']);
        }

        $subject->delete();

        return redirect()->route('academics.index')->with('status', 'تم حذف المادة التي لا تحتوي على اشتراكات.');
    }

    public function storeAcademicYear(StoreAcademicYearRequest $request): RedirectResponse
    {
        if ($request->boolean('is_active')) {
            $currentYear = AcademicYear::query()->where('is_active', true)->first();
            if ($currentYear && ! $currentYear->academic_closed_at) {
                return back()->withErrors(['is_active' => 'أقفل السنة الدراسية الحالية بعد قفل فترتها المالية قبل تفعيل سنة جديدة.']);
            }

            AcademicYear::query()->update(['is_active' => false]);
        }

        AcademicYear::query()->create([...$request->validated(), 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('academics.index')->with('status', 'تمت إضافة السنة الدراسية.');
    }

    public function closeAcademicYear(Request $request, AcademicYear $academicYear, CloseAcademicYear $closeAcademicYear): RedirectResponse
    {
        $data = $request->validate(['scope' => ['required', 'in:financial,academic']]);
        $closeAcademicYear->handle($academicYear, $data['scope'], $request->user());

        return redirect()->route('academics.index')->with('status', $data['scope'] === 'financial' ? 'تم قفل الفترة المالية للسنة بنجاح.' : 'تم قفل السنة الدراسية. يمكنك الآن تفعيل سنة جديدة.');
    }

    public function storeGrade(StoreGradeRequest $request): RedirectResponse
    {
        Grade::query()->create($request->validated());

        return redirect()->route('academics.index')->with('status', 'تمت إضافة الصف الدراسي.');
    }

    public function teachers(): View
    {
        $academicYear = $this->activeAcademicYear();
        $teachers = Teacher::query()->with([
            'subjects' => fn ($query) => $query->when($academicYear, fn ($subjects) => $subjects->where('academic_year_id', $academicYear->id))->with('enrollments.payments'),
            'payouts' => fn ($query) => $query->when($academicYear, fn ($payouts) => $payouts->where('academic_year_id', $academicYear->id)),
        ])->get()->map(function (Teacher $teacher): array {
            $enrollments = $teacher->subjects->flatMap->enrollments;
            $collections = (float) $enrollments->sum(fn (Enrollment $enrollment): float => (float) $enrollment->payments->sum('amount'));
            $payouts = (float) $teacher->payouts->sum('amount');

            return ['id' => $teacher->id, 'name' => $teacher->name, 'phone' => $teacher->phone, 'is_active' => $teacher->is_active, 'subjects' => $teacher->subjects->pluck('name')->join('، ') ?: 'لا توجد مواد', 'students' => $enrollments->pluck('student_id')->unique()->count(), 'collections' => number_format($collections, 2).' ج.م', 'wallet' => number_format($collections - $payouts, 2).' ج.م'];
        });

        return view('screens.teachers', compact('teachers', 'academicYear'));
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
        $academicYear = $this->activeAcademicYear();
        $teacher->load([
            'subjects' => fn ($query) => $query->when($academicYear, fn ($subjects) => $subjects->where('academic_year_id', $academicYear->id))->with('enrollments.payments'),
            'payouts' => fn ($query) => $query->when($academicYear, fn ($payouts) => $payouts->where('academic_year_id', $academicYear->id))->with('payer'),
        ]);
        $subjects = $teacher->subjects->map(function (Subject $subject): array {
            $paid = (float) $subject->enrollments->sum(fn (Enrollment $enrollment): float => (float) $enrollment->payments->sum('amount'));
            $due = (float) $subject->enrollments->sum(fn (Enrollment $enrollment): float => max(0, (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount')));

            return ['name' => $subject->name, 'students' => $subject->enrollments->pluck('student_id')->unique()->count(), 'paid' => $paid, 'due' => $due];
        });

        return view('screens.teacher', ['teacher' => $teacher, 'subjects' => $subjects, 'collections' => $subjects->sum('paid'), 'due' => $subjects->sum('due'), 'payoutTotal' => (float) $teacher->payouts->sum('amount'), 'academicYear' => $academicYear]);
    }

    public function users(): View
    {
        $permissions = Permission::query()->orderBy('name')->get();
        $users = User::query()->with(['roles.permissions', 'permissions'])->get()->map(function (User $user) use ($permissions): array {
            $permissionIds = $permissions
                ->filter(fn (Permission $permission): bool => $user->hasPermission($permission->slug))
                ->pluck('id')
                ->all();
            $scope = $user->hasRole('admin')
                ? 'كامل الصلاحيات'
                : ($permissions->whereIn('id', $permissionIds)->pluck('name')->join('، ') ?: 'لا توجد صلاحيات');

            return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'job_title' => $user->job_title, 'role' => $user->roles->pluck('name')->join('، ') ?: 'بلا دور', 'scope' => $scope, 'is_active' => $user->is_active, 'permission_ids' => $permissionIds, 'permissions_locked' => $user->hasRole('admin'), 'is_current_user' => $user->is(auth()->user())];
        });

        return view('screens.users', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
            'permissions' => $permissions,
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
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);

        if ($user->is($request->user()) && ! $request->boolean('is_active')) {
            return back()->withErrors(['is_active' => 'لا يمكنك إيقاف حسابك أثناء تسجيل الدخول به.']);
        }

        $updates = collect($data)->except(['password', 'permission_ids', 'is_active'])->all();
        $updates['is_active'] = $request->boolean('is_active');

        if (filled($data['password'] ?? null)) {
            $updates['password'] = $data['password'];
        }

        $user->update($updates);

        if (! $user->hasRole('admin')) {
            $selectedPermissionIds = collect($data['permission_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique();
            $permissionStates = Permission::query()->pluck('id')->mapWithKeys(fn (int $id): array => [
                $id => ['is_granted' => $selectedPermissionIds->contains($id)],
            ])->all();

            $user->permissions()->sync($permissionStates);
        }

        return redirect()->route('users.index')->with('status', filled($data['password'] ?? null) ? 'تم تحديث المستخدم وإعادة تعيين كلمة المرور والصلاحيات.' : 'تم تحديث المستخدم وحالة دخوله وصلاحياته.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'لا يمكنك حذف حسابك أثناء تسجيل الدخول به.']);
        }

        $hasFinancialHistory = Payment::query()->where('received_by', $user->id)->exists()
            || TeacherPayout::query()->where('paid_by', $user->id)->exists()
            || Refund::query()->where('refunded_by', $user->id)->exists()
            || DailyCashMovement::query()->where('collector_id', $user->id)->orWhere('recorded_by', $user->id)->exists();

        if ($hasFinancialHistory) {
            return back()->withErrors(['user' => 'لا يمكن حذف المستخدم لأنه مرتبط بحركات مالية. أوقف الحساب بدلًا من حذفه للحفاظ على سجل المراجعة.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'تم حذف المستخدم غير المرتبط بسجلات مالية.');
    }

    public function reports(Request $request): View
    {
        $activeYear = $this->activeAcademicYear();
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'secretary_id' => ['nullable', 'integer', 'exists:users,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'type' => ['nullable', 'in:all,collection,payout,refund,daily_income,daily_expense'],
        ]);
        $from = Carbon::parse($filters['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? now())->endOfDay();
        $type = $filters['type'] ?? 'all';
        $academicYearId = $request->integer('academic_year_id') ?: $activeYear?->id;

        $payments = Payment::query()
            ->with(['student', 'receiver', 'enrollment.subject.teacher'])
            ->whereBetween('paid_at', [$from, $to])
            ->when($academicYearId, fn ($query) => $query->whereHas('enrollment.subject', fn ($subject) => $subject->where('academic_year_id', $academicYearId)))
            ->when(isset($filters['secretary_id']), fn ($query) => $query->where('received_by', $filters['secretary_id']))
            ->when(isset($filters['teacher_id']), fn ($query) => $query->whereHas('enrollment.subject', fn ($subject) => $subject->where('teacher_id', $filters['teacher_id'])))
            ->get();
        $payouts = TeacherPayout::query()
            ->with(['teacher', 'payer'])
            ->whereBetween('paid_at', [$from, $to])
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->when(isset($filters['secretary_id']), fn ($query) => $query->where('paid_by', $filters['secretary_id']))
            ->when(isset($filters['teacher_id']), fn ($query) => $query->where('teacher_id', $filters['teacher_id']))
            ->get();
        $refunds = Refund::query()
            ->with(['student', 'enrollment.subject', 'processor'])
            ->whereBetween('refunded_at', [$from, $to])
            ->when($academicYearId, fn ($query) => $query->whereHas('enrollment.subject', fn ($subject) => $subject->where('academic_year_id', $academicYearId)))
            ->when(isset($filters['secretary_id']), fn ($query) => $query->where('refunded_by', $filters['secretary_id']))
            ->when(isset($filters['teacher_id']), fn ($query) => $query->whereHas('enrollment.subject', fn ($subject) => $subject->where('teacher_id', $filters['teacher_id'])))
            ->get();
        $cashMovements = DailyCashMovement::query()
            ->with(['collector', 'recorder'])
            ->whereDate('movement_date', '>=', $from->toDateString())
            ->whereDate('movement_date', '<=', $to->toDateString())
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->when(isset($filters['secretary_id']), fn ($query) => $query->where(fn ($movement) => $movement->where('collector_id', $filters['secretary_id'])->orWhere('recorded_by', $filters['secretary_id'])))
            ->get();
        $enrollments = Enrollment::query()
            ->with('payments')
            ->when($academicYearId, fn ($query) => $query->whereHas('subject', fn ($subject) => $subject->where('academic_year_id', $academicYearId)))
            ->when(isset($filters['teacher_id']), fn ($query) => $query->whereHas('subject', fn ($subject) => $subject->where('teacher_id', $filters['teacher_id'])))
            ->get();
        $dueByEnrollment = $enrollments->mapWithKeys(fn (Enrollment $enrollment): array => [$enrollment->id => max(0, (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount'))]);
        $subjectTotals = $payments->groupBy(fn (Payment $payment): string => $payment->enrollment->subject->name)->map(fn ($subjectPayments): float => (float) $subjectPayments->sum('amount'));
        $maxSubjectTotal = max(1, (float) $subjectTotals->max());
        $auditRows = collect()
            ->when(in_array($type, ['all', 'collection'], true), fn (Collection $rows) => $rows->push(...$payments->map(fn (Payment $payment): array => [
                'occurred_at' => $payment->paid_at,
                'type' => 'تحصيل طالب',
                'person' => $payment->student->name,
                'detail' => $payment->enrollment->subject->name,
                'executor' => $payment->receiver->name,
                'amount' => (float) $payment->amount,
                'direction' => 'in',
            ])))
            ->when(in_array($type, ['all', 'payout'], true), fn (Collection $rows) => $rows->push(...$payouts->map(fn (TeacherPayout $payout): array => [
                'occurred_at' => $payout->paid_at,
                'type' => 'صرف مدرس',
                'person' => $payout->teacher->name,
                'detail' => $payout->note ?: 'صرف مستحقات',
                'executor' => $payout->payer->name,
                'amount' => (float) $payout->amount,
                'direction' => 'out',
            ])))
            ->when(in_array($type, ['all', 'refund'], true), fn (Collection $rows) => $rows->push(...$refunds->map(fn (Refund $refund): array => [
                'occurred_at' => $refund->refunded_at,
                'type' => 'رد مبلغ طالب',
                'person' => $refund->student->name,
                'detail' => $refund->enrollment->subject->name,
                'executor' => $refund->processor->name,
                'amount' => (float) $refund->amount,
                'direction' => 'out',
            ])))
            ->when(in_array($type, ['all', 'daily_income'], true), fn (Collection $rows) => $rows->push(...$cashMovements->where('type', 'income')->map(fn (DailyCashMovement $movement): array => [
                'occurred_at' => $movement->movement_date,
                'type' => 'إيراد سنتر',
                'person' => 'سنتر دريم',
                'detail' => 'تحصيل يومي'.($movement->note ? ' · '.$movement->note : ''),
                'executor' => $movement->recorder->name,
                'amount' => (float) $movement->amount,
                'direction' => 'in',
            ])))
            ->when(in_array($type, ['all', 'daily_expense'], true), fn (Collection $rows) => $rows->push(...$cashMovements->where('type', 'expense')->map(fn (DailyCashMovement $movement): array => [
                'occurred_at' => $movement->movement_date,
                'type' => 'مصروف سنتر',
                'person' => 'سنتر دريم',
                'detail' => ['electricity' => 'كهرباء', 'rent' => 'إيجار', 'owner_transfer' => 'توريد لصاحب السنتر', 'supplies' => 'مستلزمات', 'other' => 'مصروف آخر'][$movement->category].($movement->note ? ' · '.$movement->note : ''),
                'executor' => $movement->recorder->name,
                'amount' => (float) $movement->amount,
                'direction' => 'out',
            ])))
            ->sortByDesc('occurred_at')
            ->values();

        return view('screens.reports', [
            'periodLabel' => DatePresenter::date($from).' — '.DatePresenter::date($to),
            'filters' => [...$filters, 'academic_year_id' => $academicYearId, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'type' => $type],
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'secretaries' => User::query()->where('is_active', true)->orderBy('name')->get(),
            // Keep historical teachers available so old financial movements remain filterable.
            'teachers' => Teacher::query()->orderBy('name')->get(),
            'collectionTotal' => (float) $payments->sum('amount'),
            'teacherPayoutTotal' => (float) $payouts->sum('amount'),
            'refundTotal' => (float) $refunds->sum('amount'),
            'dailyIncomeTotal' => (float) $cashMovements->where('type', 'income')->sum('amount'),
            'dailyExpenseTotal' => (float) $cashMovements->where('type', 'expense')->sum('amount'),
            'netCollections' => (float) $payments->sum('amount') - (float) $payouts->sum('amount') - (float) $refunds->sum('amount'),
            'completedEnrollments' => $dueByEnrollment->filter(fn (float $amount): bool => $amount === 0.0)->count(),
            'enrollmentCount' => $enrollments->count(),
            'dueTotal' => (float) $dueByEnrollment->sum(),
            'studentsWithDue' => $enrollments->filter(fn (Enrollment $enrollment): bool => $dueByEnrollment[$enrollment->id] > 0)->pluck('student_id')->unique()->count(),
            'reportRows' => $subjectTotals->map(fn (float $total, string $name): array => [$name, (int) round($total / $maxSubjectTotal * 100), 'blue', number_format($total, 2).' ج.م'])->values(),
            'auditRows' => $auditRows,
        ]);
    }

    public function dailyCashbook(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = Carbon::parse($filters['from'] ?? $filters['date'] ?? today())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? $from)->endOfDay();
        $movements = DailyCashMovement::query()
            ->with(['collector', 'recorder'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereDate('movement_date', '>=', $from->toDateString())
            ->whereDate('movement_date', '<=', $to->toDateString())
            ->latest('id')
            ->get();

        return view('screens.daily-cashbook', [
            'selectedFrom' => $from,
            'selectedTo' => $to,
            'movements' => $movements,
            'incomeTotal' => (float) $movements->where('type', 'income')->sum('amount'),
            'expenseTotal' => (float) $movements->where('type', 'expense')->sum('amount'),
            'categories' => [
                'daily_collection' => 'تحصيل يومي',
                'electricity' => 'كهرباء',
                'rent' => 'إيجار',
                'owner_transfer' => 'توريد لصاحب السنتر',
                'supplies' => 'مستلزمات',
                'other' => 'مصروف آخر',
            ],
            'academicYear' => $academicYear,
        ]);
    }

    public function storeDailyCashMovement(StoreDailyCashMovementRequest $request, RecordDailyCashMovement $recordMovement): RedirectResponse
    {
        $movement = $recordMovement->handle($request->validated(), $request->user());
        $message = $movement->type === 'income' ? 'تم تسجيل إيراد اليوم بنجاح.' : 'تم تسجيل المصروف بنجاح.';

        return redirect()
            ->route('daily-cashbook.index', ['from' => $movement->movement_date->toDateString(), 'to' => $movement->movement_date->toDateString()])
            ->with('status', $message);
    }

    public function collections(): View
    {
        $academicYear = $this->activeAcademicYear();

        return view('screens.collections', [
            'enrollments' => Enrollment::query()
                ->with(['student', 'subject.teacher', 'payments'])
                ->when($academicYear, fn ($query) => $query->whereHas('subject', fn ($subjects) => $subjects->where('academic_year_id', $academicYear->id)))
                ->latest()
                ->get()
                ->filter(fn (Enrollment $enrollment): bool => (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount') > 0),
            'submissionToken' => (string) Str::uuid(),
            'completedPayment' => request()->integer('payment') ? Payment::query()
                ->with(['student', 'enrollment.subject'])
                ->when($academicYear, fn ($query) => $query->whereHas('enrollment.subject', fn ($subjects) => $subjects->where('academic_year_id', $academicYear->id)))
                ->find(request()->integer('payment')) : null,
            'receiptSize' => request()->query('size', 'A5') === 'A4' ? 'A4' : 'A5',
            'academicYear' => $academicYear,
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
        $enrollment->loadMissing('subject.academicYear');
        AcademicYearLedger::ensureOpen($enrollment->subject->academicYear);
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

    public function discounts(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $filters = $request->validate(['date' => ['nullable', 'date']]);
        $discountDate = isset($filters['date']) ? Carbon::parse($filters['date'])->startOfDay() : null;
        $discounts = Discount::query()
            ->with(['enrollment.student', 'enrollment.subject', 'approver'])
            ->when($academicYear, fn ($query) => $query->whereHas('enrollment.subject', fn ($subjects) => $subjects->where('academic_year_id', $academicYear->id)))
            ->when($discountDate, fn ($query) => $query->whereDate('created_at', $discountDate->toDateString()))
            ->latest()
            ->get();
        $enrollments = Enrollment::query()
            ->with(['student', 'subject', 'payments'])
            ->when($academicYear, fn ($query) => $query->whereHas('subject', fn ($subjects) => $subjects->where('academic_year_id', $academicYear->id)))
            ->get();

        return view('screens.discounts', compact('discounts', 'enrollments', 'discountDate', 'academicYear'));
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
        $academicYear = $this->activeAcademicYear();
        $payouts = TeacherPayout::query()->with(['teacher', 'subject', 'payer'])->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))->latest('paid_at')->get();
        $teachers = Teacher::query()->with(['subjects' => fn ($query) => $query->when($academicYear, fn ($subjects) => $subjects->where('academic_year_id', $academicYear->id))->with('enrollments.payments'), 'payouts' => fn ($query) => $query->when($academicYear, fn ($payouts) => $payouts->where('academic_year_id', $academicYear->id))])->where('is_active', true)->orderBy('name')->get()->map(function (Teacher $teacher): Teacher {
            $collected = (float) $teacher->subjects->flatMap->enrollments->sum(fn (Enrollment $enrollment): float => (float) $enrollment->payments->sum('amount'));
            $teacher->setAttribute('wallet_total', $collected - (float) $teacher->payouts->sum('amount'));
            $teacher->setAttribute('subject_count', $teacher->subjects->count());

            return $teacher;
        });

        return view('screens.payouts', compact('payouts', 'teachers', 'academicYear'));
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
                'date' => DatePresenter::date(now()),
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
                'date' => DatePresenter::date($payment->paid_at),
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
    private function materialSummary(?int $academicYearId = null): Collection
    {
        return Subject::query()->with(['teacher', 'enrollments.payments'])->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))->where('is_active', true)->get()->map(function (Subject $subject): array {
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

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
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

<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\DailyCashMovement;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherPayout;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LargeDemoDataSeeder extends Seeder
{
    private const STUDENT_COUNT = 180;

    public function run(): void
    {
        if (app()->environment('production') && ! config('app.demo_seeder_enabled')) {
            throw new \RuntimeException('بيانات العرض الموسعة تتطلب ضبط DEMO_SEEDER_ENABLED=true مؤقتًا في بيئة الإنتاج.');
        }

        $this->call(DemoDataSeeder::class);

        $year = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->firstOrCreate(
                ['name' => '2026 / 2027'],
                ['starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true],
            );
        $admin = User::query()->whereHas('roles', fn ($roles) => $roles->where('slug', 'admin'))->first()
            ?? $this->createDemoUser('مدير العرض', 'large-admin@centerdream.test', '01000000990', 'مدير العرض', 'admin');
        $secretary = User::query()->whereHas('roles', fn ($roles) => $roles->where('slug', 'secretary'))->first()
            ?? $this->createDemoUser('سكرتير العرض', 'large-secretary@centerdream.test', '01000000991', 'سكرتير', 'secretary');
        $grades = Grade::query()->orderBy('sort_order')->get();

        $teachers = collect([
            'أ. أحمد سامي', 'أ. سارة نادر', 'أ. خالد طارق', 'أ. منى عادل', 'أ. محمد فوزي', 'أ. هبة علاء',
            'أ. عمر شريف', 'أ. نجلاء حسن', 'أ. ياسر كمال', 'أ. دعاء محمود', 'أ. كريم السيد', 'أ. ريم صبري',
        ])->map(function (string $name, int $index): Teacher {
            return Teacher::query()->updateOrCreate(
                ['phone' => '01000000'.str_pad((string) (20 + $index), 3, '0', STR_PAD_LEFT)],
                ['name' => $name, 'is_active' => true],
            );
        });

        $subjects = $this->seedSubjects($year, $grades, $teachers);
        $students = $this->seedStudents($year, $grades);
        $this->seedEnrollments($students, $subjects, $secretary, $admin, $year);
        $this->seedTeacherPayouts($teachers, $admin, $year);
        $this->seedDailyCashbook($secretary, $year);

        $this->command?->info(sprintf(
            'تم تجهيز بيانات عرض موسعة: %d طالبًا، %d مدرسًا، %d مادة، و%d اشتراكًا.',
            $students->count(),
            $teachers->count(),
            $subjects->count(),
            Enrollment::query()->whereHas('subject', fn ($query) => $query->where('academic_year_id', $year->id))->count(),
        ));
    }

    /** @param Collection<int, Grade> $grades @param \Illuminate\Support\Collection<int, Teacher> $teachers */
    private function seedSubjects(AcademicYear $year, $grades, $teachers): Collection
    {
        $catalogue = [
            ['رياضيات', 450], ['فيزياء', 500], ['كيمياء', 480], ['لغة إنجليزية', 350], ['لغة عربية', 360], ['أحياء', 470],
        ];

        return $grades->flatMap(function (Grade $grade, int $gradeIndex) use ($catalogue, $teachers, $year) {
            return collect($catalogue)->map(function (array $item, int $subjectIndex) use ($grade, $gradeIndex, $teachers, $year): Subject {
                [$name, $fee] = $item;

                return Subject::query()->updateOrCreate(
                    ['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => $name],
                    [
                        'teacher_id' => $teachers[($gradeIndex * 3 + $subjectIndex) % $teachers->count()]->id,
                        'fee' => $fee + ($gradeIndex * 25),
                        'is_active' => true,
                    ],
                );
            });
        })->values();
    }

    /** @param Collection<int, Grade> $grades */
    private function seedStudents(AcademicYear $year, $grades): Collection
    {
        $firstNames = ['آدم', 'أحمد', 'أدهم', 'إسراء', 'أسماء', 'أميرة', 'بسملة', 'بيان', 'تقى', 'جنى', 'حبيبة', 'خالد', 'دعاء', 'رحمة', 'ريم', 'سارة', 'سلمى', 'شهد', 'صالح', 'عمر', 'فرح', 'كريم', 'لارا', 'محمد', 'مريم', 'ملك', 'منة', 'ندى', 'نور', 'ياسين'];
        $lastNames = ['أحمد', 'السيد', 'عادل', 'مصطفى', 'محمد', 'حسن', 'خليل', 'محمود', 'رفاعي', 'عبد الله', 'طه', 'فوزي'];

        return collect(range(1, self::STUDENT_COUNT))->map(function (int $index) use ($year, $grades, $firstNames, $lastNames): Student {
            $grade = $grades[($index - 1) % $grades->count()];
            $name = $firstNames[$index % count($firstNames)].' '.$lastNames[intdiv($index, 3) % count($lastNames)];
            $phone = '011'.str_pad((string) (70000000 + $index), 8, '0', STR_PAD_LEFT);

            return Student::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'phone' => $phone],
                [
                    'grade_id' => $grade->id,
                    'name' => $name,
                    'guardian_name' => 'ولي أمر '.$name,
                    'guardian_phone' => '012'.str_pad((string) (60000000 + $index), 8, '0', STR_PAD_LEFT),
                    'note' => 'بيانات عرض موسعة لاختبار التقارير والجداول والفلاتر.',
                ],
            );
        });
    }

    /** @param Collection<int, Student> $students @param \Illuminate\Support\Collection<int, Subject> $subjects */
    private function seedEnrollments($students, $subjects, User $secretary, User $admin, AcademicYear $year): void
    {
        $subjectsByGrade = $subjects->groupBy('grade_id');
        $methods = ['cash', 'transfer', 'wallet'];

        foreach ($students as $studentIndex => $student) {
            $gradeSubjects = $subjectsByGrade->get($student->grade_id)->values();
            $subjectCount = $studentIndex % 4 === 0 ? 3 : 2;

            for ($offset = 0; $offset < $subjectCount; $offset++) {
                $subject = $gradeSubjects[($studentIndex + $offset) % $gradeSubjects->count()];
                $enrollment = Enrollment::query()->firstOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $subject->id],
                    ['fee' => $subject->fee, 'discount_amount' => 0],
                );
                $discountAmount = $studentIndex % 11 === 0 && $offset === 0 ? round((float) $subject->fee * 0.1, 2) : 0;
                $enrollment->update(['fee' => $subject->fee, 'discount_amount' => $discountAmount]);

                if ($discountAmount > 0) {
                    Discount::query()->updateOrCreate(
                        ['enrollment_id' => $enrollment->id, 'reason' => 'خصم عرض موسع'],
                        ['approved_by' => $admin->id, 'type' => 'percentage', 'value' => 10, 'amount' => $discountAmount],
                    );
                }

                $netFee = (float) $subject->fee - $discountAmount;
                $paymentRatio = [1, 0.7, 0.4, 0][$studentIndex % 4];
                $paymentAmount = round($netFee * $paymentRatio, 2);

                if ($paymentAmount > 0) {
                    Payment::query()->updateOrCreate(
                        ['receipt_number' => sprintf('LARGE-%d-%d-%d', $year->id, $student->id, $subject->id)],
                        [
                            'student_id' => $student->id,
                            'enrollment_id' => $enrollment->id,
                            'received_by' => $secretary->id,
                            'amount' => $paymentAmount,
                            'method' => $methods[($studentIndex + $offset) % count($methods)],
                            'submission_token' => null,
                            'paid_at' => now()->subDays(($studentIndex + $offset) % 30)->setTime(10 + ($offset * 2), 15),
                        ],
                    );
                }

                if ($studentIndex % 50 === 0 && $offset === 1 && $paymentAmount > 0) {
                    $refundAmount = min(100, $paymentAmount);
                    $enrollment->update(['cancelled_at' => now()->subDays(2), 'refund_amount' => $refundAmount, 'refund_note' => 'إلغاء تجريبي للعرض']);
                    Refund::query()->updateOrCreate(
                        ['enrollment_id' => $enrollment->id, 'note' => 'إلغاء تجريبي للعرض'],
                        ['student_id' => $student->id, 'refunded_by' => $admin->id, 'amount' => $refundAmount, 'method' => 'cash', 'refunded_at' => now()->subDays(2)],
                    );
                }
            }
        }
    }

    /** @param Collection<int, Teacher> $teachers */
    private function seedTeacherPayouts($teachers, User $admin, AcademicYear $year): void
    {
        $teachers->each(function (Teacher $teacher, int $index) use ($admin, $year): void {
            TeacherPayout::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'note' => 'صرف عرض موسع #'.($index + 1)],
                [
                    'paid_by' => $admin->id,
                    'amount' => 350 + ($index * 75),
                    'period_from' => now()->startOfMonth(),
                    'period_to' => now(),
                    'method' => $index % 2 === 0 ? 'transfer' : 'cash',
                    'paid_at' => now()->subDays($index % 12),
                ],
            );
        });
    }

    private function seedDailyCashbook(User $secretary, AcademicYear $year): void
    {
        collect(range(0, 29))->each(function (int $offset) use ($secretary, $year): void {
            $date = Carbon::today()->subDays($offset);
            DailyCashMovement::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'note' => 'إيراد عرض موسع '.$date->toDateString()],
                ['type' => 'income', 'category' => 'daily_collection', 'amount' => 900 + (($offset % 5) * 175), 'movement_date' => $date, 'collector_id' => $secretary->id, 'recorded_by' => $secretary->id],
            );

            if ($offset % 3 === 0) {
                DailyCashMovement::query()->updateOrCreate(
                    ['academic_year_id' => $year->id, 'note' => 'مصروف عرض موسع '.$date->toDateString()],
                    ['type' => 'expense', 'category' => ['electricity', 'supplies', 'other'][$offset % 3], 'amount' => 120 + (($offset % 4) * 55), 'movement_date' => $date, 'recorded_by' => $secretary->id],
                );
            }
        });
    }

    private function createDemoUser(string $name, string $email, string $phone, string $jobTitle, string $roleSlug): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'job_title' => $jobTitle,
            'password' => 'DemoPass-2026!',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::query()->where('slug', $roleSlug)->sole());

        return $user;
    }
}

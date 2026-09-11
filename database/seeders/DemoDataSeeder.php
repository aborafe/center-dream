<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
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
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('بيانات العرض لا يمكن تشغيلها في بيئة الإنتاج.');
        }

        $this->call(ReferenceDataSeeder::class);

        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $secretaryRole = Role::query()->where('slug', 'secretary')->firstOrFail();
        $teacherRole = Role::query()->where('slug', 'teacher')->firstOrFail();

        $admin = $this->user('مدير سنتر دريم', 'admin@centerdream.test', '01000000010', $adminRole);
        $secretary = $this->user('مريم عادل', 'secretary@centerdream.test', '01000000011', $secretaryRole, 'سكرتير');
        $mathUser = $this->user('أحمد سامي', 'math@centerdream.test', '01000000012', $teacherRole, 'مدرس رياضيات');
        $englishUser = $this->user('سارة نادر', 'english@centerdream.test', '01000000013', $teacherRole, 'مدرس لغة إنجليزية');

        $year = AcademicYear::query()->where('name', '2026 / 2027')->firstOrFail();
        $thirdGrade = Grade::query()->where('name', 'الصف الثالث الثانوي')->firstOrFail();
        $secondGrade = Grade::query()->where('name', 'الصف الثاني الثانوي')->firstOrFail();
        $mathTeacher = Teacher::query()->updateOrCreate(['phone' => '01000000001'], ['user_id' => $mathUser->id, 'name' => 'أ. أحمد سامي', 'is_active' => true]);
        $englishTeacher = Teacher::query()->updateOrCreate(['phone' => '01000000002'], ['user_id' => $englishUser->id, 'name' => 'أ. سارة نادر', 'is_active' => true]);
        $physicsTeacher = Teacher::query()->updateOrCreate(['phone' => '01000000003'], ['name' => 'أ. خالد طارق', 'is_active' => true]);

        $math = $this->subject($year, $thirdGrade, $mathTeacher, 'رياضيات', 450);
        $english = $this->subject($year, $thirdGrade, $englishTeacher, 'لغة إنجليزية', 350);
        $physics = $this->subject($year, $thirdGrade, $physicsTeacher, 'فيزياء', 500);
        $secondMath = $this->subject($year, $secondGrade, $mathTeacher, 'رياضيات', 400);

        $sara = $this->student($year, $thirdGrade, 'سارة محمد', '01095225454', 'محمد السيد');
        $nour = $this->student($year, $thirdGrade, 'نور أحمد', '01110000000', 'أحمد حسن');
        $youssef = $this->student($year, $thirdGrade, 'يوسف علي', '01220000000', 'علي يوسف');
        $mariam = $this->student($year, $thirdGrade, 'مريم سامح', '01530000000', 'سامح محمود');
        $omar = $this->student($year, $secondGrade, 'عمر خالد', '01040000000', 'خالد عمر');

        $saraMath = $this->enrollment($sara, $math, 450);
        $saraEnglish = $this->enrollment($sara, $english, 350);
        $nourPhysics = $this->enrollment($nour, $physics, 500);
        $youssefMath = $this->enrollment($youssef, $math, 450);
        $mariamEnglish = $this->enrollment($mariam, $english, 350);
        $omarMath = $this->enrollment($omar, $secondMath, 400);

        $this->payment($sara, $saraMath, $secretary, 'DEMO-PAY-001', 450, 'cash', now()->subDays(4));
        $this->payment($sara, $saraEnglish, $secretary, 'DEMO-PAY-002', 200, 'wallet', now()->subDays(2));
        $this->payment($nour, $nourPhysics, $secretary, 'DEMO-PAY-003', 250, 'cash', now()->subDay());
        $this->payment($youssef, $youssefMath, $secretary, 'DEMO-PAY-004', 450, 'transfer', now()->subDays(3));
        $this->payment($mariam, $mariamEnglish, $secretary, 'DEMO-PAY-005', 100, 'cash', now()->subDays(5));
        $this->payment($omar, $omarMath, $secretary, 'DEMO-PAY-006', 400, 'cash', now()->subDays(6));

        Discount::query()->firstOrCreate(['enrollment_id' => $saraEnglish->id, 'reason' => 'خصم أخوات'], ['approved_by' => $admin->id, 'type' => 'amount', 'value' => 50, 'amount' => 50]);
        $saraEnglish->update(['discount_amount' => 50]);
        $omarMath->update(['cancelled_at' => now()->subDay(), 'refund_amount' => 100, 'refund_note' => 'إلغاء قبل بداية المجموعة']);
        Refund::query()->firstOrCreate(['enrollment_id' => $omarMath->id, 'note' => 'إلغاء قبل بداية المجموعة'], ['student_id' => $omar->id, 'refunded_by' => $admin->id, 'amount' => 100, 'method' => 'cash', 'refunded_at' => now()->subDay()]);

        TeacherPayout::query()->firstOrCreate(['teacher_id' => $mathTeacher->id, 'note' => 'صرف الأسبوع الأول — بيانات عرض'], ['paid_by' => $admin->id, 'amount' => 500, 'period_from' => now()->startOfMonth(), 'period_to' => now(), 'method' => 'transfer', 'paid_at' => now()->subDay()]);
    }

    private function user(string $name, string $email, string $phone, Role $role, ?string $jobTitle = null): User
    {
        $user = User::query()->updateOrCreate(['email' => $email], ['name' => $name, 'phone' => $phone, 'job_title' => $jobTitle, 'password' => Hash::make('DemoPass-2026!'), 'is_active' => true]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function subject(AcademicYear $year, Grade $grade, Teacher $teacher, string $name, float $fee): Subject
    {
        return Subject::query()->updateOrCreate(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => $name], ['teacher_id' => $teacher->id, 'fee' => $fee, 'is_active' => true]);
    }

    private function student(AcademicYear $year, Grade $grade, string $name, string $phone, string $guardian): Student
    {
        return Student::query()->updateOrCreate(['phone' => $phone], ['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => $name, 'guardian_name' => $guardian, 'guardian_phone' => $phone, 'note' => 'بيانات عرض لاختبار واجهة المركز.']);
    }

    private function enrollment(Student $student, Subject $subject, float $fee): Enrollment
    {
        return Enrollment::query()->firstOrCreate(['student_id' => $student->id, 'subject_id' => $subject->id], ['fee' => $fee, 'discount_amount' => 0]);
    }

    private function payment(Student $student, Enrollment $enrollment, User $receiver, string $receipt, float $amount, string $method, \DateTimeInterface $paidAt): Payment
    {
        return Payment::query()->firstOrCreate(['receipt_number' => $receipt], ['student_id' => $student->id, 'enrollment_id' => $enrollment->id, 'received_by' => $receiver->id, 'amount' => $amount, 'method' => $method, 'submission_token' => null, 'paid_at' => $paidAt]);
    }
}

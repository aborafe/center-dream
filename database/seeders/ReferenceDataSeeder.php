<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            'dashboard' => 'لوحة التحكم',
            'students' => 'إدارة الطلاب', 'enrollments' => 'تسجيل الاشتراكات', 'collections' => 'تسجيل التحصيل',
            'discounts' => 'اعتماد الخصومات', 'reports' => 'عرض التقارير', 'payouts' => 'صرف المدرسين',
            'academics' => 'الإدارة الأكاديمية', 'users' => 'إدارة المستخدمين', 'settings' => 'إعدادات المركز',
        ])->mapWithKeys(fn (string $name, string $slug) => [$slug => Permission::firstOrCreate(['slug' => $slug], ['name' => $name])]);

        $roles = [
            'admin' => ['مسؤول المركز', $permissions->keys()->all()],
            'secretary' => ['سكرتير', ['dashboard', 'students', 'enrollments', 'collections']],
            'teacher' => ['مدرس', []],
        ];

        foreach ($roles as $slug => [$name, $permissionSlugs]) {
            $role = Role::firstOrCreate(['slug' => $slug], ['name' => $name]);
            $role->permissions()->sync($permissions->only($permissionSlugs)->pluck('id'));
        }

        $academicYear = AcademicYear::firstOrCreate(['name' => '2026 / 2027'], [
            'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);

        foreach (['الصف الأول الثانوي', 'الصف الثاني الثانوي', 'الصف الثالث الثانوي'] as $index => $name) {
            Grade::firstOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }

        $thirdGrade = Grade::query()->where('name', 'الصف الثالث الثانوي')->firstOrFail();
        $mathTeacher = Teacher::firstOrCreate(['phone' => '01000000001'], ['name' => 'أ. أحمد سامي']);
        $englishTeacher = Teacher::firstOrCreate(['phone' => '01000000002'], ['name' => 'أ. سارة نادر']);

        Subject::firstOrCreate(['academic_year_id' => $academicYear->id, 'grade_id' => $thirdGrade->id, 'name' => 'رياضيات'], ['teacher_id' => $mathTeacher->id, 'fee' => 450, 'is_active' => true]);
        Subject::firstOrCreate(['academic_year_id' => $academicYear->id, 'grade_id' => $thirdGrade->id, 'name' => 'لغة إنجليزية'], ['teacher_id' => $englishTeacher->id, 'fee' => 350, 'is_active' => true]);
    }
}

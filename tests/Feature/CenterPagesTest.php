<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CenterSetting;
use App\Models\DailyCashMovement;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherPayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CenterPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::query()->create(['name' => 'مدير المركز', 'slug' => 'admin']);
        $user = User::factory()->create();
        $user->roles()->attach($admin);

        $this->actingAs($user);
    }

    public function test_workspace_pages_are_available(): void
    {
        foreach (['/', '/students', '/subscriptions/create', '/collections', '/daily-cashbook', '/discounts', '/teacher-payouts', '/inventory', '/academics', '/teachers', '/users', '/reports', '/profile', '/settings'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_teacher_portal_is_not_available_to_non_teacher_accounts(): void
    {
        $this->get('/my-subjects')->assertForbidden();
    }

    public function test_teacher_sees_only_his_subjects_portal(): void
    {
        $teacherRole = Role::query()->create(['name' => 'مدرس', 'slug' => 'teacher']);
        $teacherUser = User::factory()->create();
        $teacherUser->roles()->attach($teacherRole);
        $teacher = Teacher::query()->create(['user_id' => $teacherUser->id, 'name' => 'أ. أحمد سامي']);
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);

        $this->actingAs($teacherUser)
            ->get('/my-subjects')
            ->assertOk()
            ->assertSee('رياضيات')
            ->assertDontSee('لوحة الحسابات')
            ->assertDontSee('بحث عام');
    }

    public function test_teacher_login_redirects_to_his_private_portal_instead_of_the_administrator_dashboard(): void
    {
        $teacherRole = Role::query()->create(['name' => 'مدرس', 'slug' => 'teacher']);
        $teacherUser = User::factory()->create(['email' => 'teacher@example.test', 'password' => 'TeacherPass-2026']);
        $teacherUser->roles()->attach($teacherRole);

        $this->post(route('login.authenticate'), [
            'identifier' => 'teacher@example.test',
            'password' => 'TeacherPass-2026',
        ])->assertRedirect(route('teacher.portal'));
    }

    public function test_global_search_redirects_to_student_results(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        Student::query()->create([
            'academic_year_id' => $year->id, 'grade_id' => $grade->id,
            'name' => 'سارة محمد', 'phone' => '01095225454',
        ]);
        Student::query()->create([
            'academic_year_id' => $year->id, 'grade_id' => $grade->id,
            'name' => 'عمر خالد', 'phone' => '01000000000',
        ]);

        $this->get('/search?q=%D8%B3%D8%A7%D8%B1%D8%A9')
            ->assertRedirect('/students?q=%D8%B3%D8%A7%D8%B1%D8%A9');

        $this->get('/students?q=%D8%B3%D8%A7%D8%B1%D8%A9')
            ->assertOk()
            ->assertSee('سارة محمد')
            ->assertDontSee('عمر خالد');
    }

    public function test_students_can_be_filtered_by_subject_and_the_filter_identifies_its_grade(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $math = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        $physics = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'فيزياء', 'fee' => 500, 'is_active' => true]);
        $sara = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'سارة محمد', 'phone' => '01095225454']);
        $omar = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'عمر خالد', 'phone' => '01095225455']);
        Enrollment::query()->create(['student_id' => $sara->id, 'subject_id' => $math->id, 'fee' => 450, 'discount_amount' => 0]);
        Enrollment::query()->create(['student_id' => $omar->id, 'subject_id' => $physics->id, 'fee' => 500, 'discount_amount' => 0]);

        $this->get('/students?subject_id='.$math->id)
            ->assertOk()
            ->assertSee('سارة محمد')
            ->assertSee('الصف الثالث الثانوي')
            ->assertDontSee('عمر خالد');
    }

    public function test_dashboard_shortcuts_open_actionable_daily_and_debt_views(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $subject = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        $dueStudent = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'سارة مدينة', 'phone' => '01095225454']);
        $settledStudent = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'عمر منتظم', 'phone' => '01095225455']);
        $dueEnrollment = Enrollment::query()->create(['student_id' => $dueStudent->id, 'subject_id' => $subject->id, 'fee' => 450, 'discount_amount' => 0]);
        $settledEnrollment = Enrollment::query()->create(['student_id' => $settledStudent->id, 'subject_id' => $subject->id, 'fee' => 450, 'discount_amount' => 0]);
        $cancelledStudent = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'طالب ملغى', 'phone' => '01095225456']);
        Enrollment::query()->create(['student_id' => $cancelledStudent->id, 'subject_id' => $subject->id, 'fee' => 450, 'discount_amount' => 0, 'cancelled_at' => now()]);
        Payment::query()->create(['student_id' => $settledStudent->id, 'enrollment_id' => $settledEnrollment->id, 'received_by' => User::query()->firstOrFail()->id, 'receipt_number' => 'DASHBOARD-SETTLED', 'amount' => 450, 'method' => 'cash', 'paid_at' => now()]);

        $this->get('/')
            ->assertOk()
            ->assertSee('الصف الثالث الثانوي')
            ->assertSee('2 طلاب')
            ->assertSee(route('students.index', ['account' => 'due']), false)
            ->assertSee(route('discounts.index', ['date' => now()->toDateString()]), false)
            ->assertSee(route('inventory.index'), false);

        $this->get(route('students.index', ['account' => 'due']))
            ->assertOk()
            ->assertSee('سارة مدينة')
            ->assertDontSee('عمر منتظم');

        $this->get(route('students.index', ['account' => 'complete']))
            ->assertOk()
            ->assertSee('عمر منتظم')
            ->assertDontSee('سارة مدينة');

        $this->get(route('reports.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('عمر منتظم')
            ->assertSee('تحصيل طالب');

        $this->assertDatabaseHas('enrollments', ['id' => $dueEnrollment->id]);
    }

    public function test_subscription_lookup_returns_existing_student_by_normalized_phone(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'سارة محمد', 'phone' => '01095225454']);

        $this->getJson('/subscriptions/student-lookup?phone=%2B201095225454')
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('student.id', 1)
            ->assertJsonPath('student.name', 'سارة محمد')
            ->assertJsonPath('student.grade', 'الصف الثالث الثانوي')
            ->assertJsonPath('student.profile_url', route('students.show', 1));
    }

    public function test_receipt_is_rendered_as_a_standalone_print_page(): void
    {
        $this->get('/receipts/print?student=%D8%B3%D8%A7%D8%B1%D8%A9%20%D9%85%D8%AD%D9%85%D8%AF&phone=01095225454&subject=%D8%B1%D9%8A%D8%A7%D8%B6%D9%8A%D8%A7%D8%AA&amount=200%20%D8%AC.%D9%85&method=%D9%86%D9%82%D8%AF%D9%8A&size=A5')
            ->assertOk()
            ->assertSee('سارة محمد')
            ->assertSee('رياضيات')
            ->assertSee('@page { size: A5 portrait; margin: 8mm; }', false)
            ->assertSee('https://wa.me/201095225454', false)
            ->assertDontSee('app-shell');
    }

    public function test_subscription_endpoint_validates_before_persistence_is_connected(): void
    {
        $this->post('/subscriptions', [])
            ->assertSessionHasErrors(['student_name', 'student_phone', 'grade_id', 'subjects']);
    }

    public function test_student_profile_exposes_a_normalized_whatsapp_link(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $student = Student::query()->create([
            'academic_year_id' => $year->id, 'grade_id' => $grade->id,
            'name' => 'سارة محمد', 'phone' => '01095225454',
        ]);

        $this->get('/students/'.$student->id)
            ->assertOk()
            ->assertSee('سارة محمد')
            ->assertSee('https://wa.me/201095225454', false);
    }

    public function test_student_profile_lists_only_unsubscribed_subjects_for_his_grade_and_year(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $math = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'فيزياء', 'fee' => 500, 'is_active' => true]);
        $student = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'سارة محمد', 'phone' => '01095225454']);
        Enrollment::query()->create(['student_id' => $student->id, 'subject_id' => $math->id, 'fee' => 450, 'discount_amount' => 0]);

        $this->get('/students/'.$student->id)
            ->assertOk()
            ->assertSee('مواد متاحة للطالب')
            ->assertSee('فيزياء');
    }

    public function test_profile_and_settings_routes_validate_their_forms(): void
    {
        $this->put('/profile', [])
            ->assertSessionHasErrors(['full_name', 'phone', 'email', 'job_title']);

        $this->put('/settings', [])
            ->assertSessionHasErrors(['center_name', 'center_phone', 'address', 'currency']);
    }

    public function test_center_settings_are_persisted(): void
    {
        $this->put('/settings', [
            'center_name' => 'سنتر دريم التعليمي',
            'center_phone' => '01095225454',
            'address' => 'مدينة نصر — القاهرة',
            'currency' => 'جنيه مصري',
            'balance_alerts' => '1',
            'daily_summary' => '1',
        ])->assertRedirect(route('settings.edit'));

        $this->assertDatabaseHas('center_settings', [
            'center_name' => 'سنتر دريم التعليمي',
            'center_phone' => '01095225454',
            'daily_report_copy' => false,
        ]);
        $this->assertSame(1, CenterSetting::query()->count());
    }

    public function test_user_permissions_protect_routes_and_not_only_navigation(): void
    {
        $studentPermission = Permission::query()->create(['name' => 'الطلاب', 'slug' => 'students']);
        $secretary = Role::query()->create(['name' => 'سكرتير', 'slug' => 'secretary']);
        $secretary->permissions()->attach($studentPermission);

        $user = User::factory()->create();
        $user->roles()->attach($secretary);
        $this->actingAs($user);

        $this->get('/students')->assertOk();
        $this->get('/reports')->assertForbidden();
        $this->get('/')->assertForbidden();
    }

    public function test_secretary_role_has_only_its_assigned_operational_pages(): void
    {
        $permissions = collect([
            'dashboard', 'students', 'enrollments', 'collections', 'discounts', 'reports', 'payouts', 'academics', 'users', 'settings',
        ])->mapWithKeys(fn (string $slug) => [$slug => Permission::query()->create(['name' => $slug, 'slug' => $slug])]);
        $secretaryRole = Role::query()->create(['name' => 'سكرتير', 'slug' => 'secretary']);
        $secretaryRole->permissions()->sync($permissions->only(['dashboard', 'students', 'enrollments', 'collections'])->pluck('id'));
        $secretary = User::factory()->create();
        $secretary->roles()->attach($secretaryRole);

        $this->actingAs($secretary);

        foreach (['/', '/students', '/subscriptions/create', '/collections', '/daily-cashbook'] as $url) {
            $this->get($url)->assertOk();
        }

        foreach (['/discounts', '/teacher-payouts', '/inventory', '/academics', '/teachers', '/users', '/reports', '/settings'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_administrator_can_create_secretary_with_direct_permissions(): void
    {
        $secretary = Role::query()->create(['name' => 'سكرتير', 'slug' => 'secretary']);
        $students = Permission::query()->create(['name' => 'إدارة الطلاب', 'slug' => 'students']);
        $collections = Permission::query()->create(['name' => 'تسجيل التحصيل', 'slug' => 'collections']);

        $this->post('/users', [
            'name' => 'مريم عادل',
            'email' => 'mariam@example.test',
            'phone' => '01095225454',
            'job_title' => 'سكرتير',
            'password' => 'StrongPassword-2026',
            'password_confirmation' => 'StrongPassword-2026',
            'role_id' => $secretary->id,
            'permission_ids' => [$students->id, $collections->id],
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'mariam@example.test')->firstOrFail();
        $this->assertTrue($user->hasPermission('students'));
        $this->assertTrue($user->hasPermission('collections'));
        $this->assertFalse($user->hasPermission('reports'));
    }

    public function test_administrator_can_reset_a_user_password_and_override_role_permissions(): void
    {
        $dashboard = Permission::query()->create(['name' => 'لوحة التحكم', 'slug' => 'dashboard']);
        $students = Permission::query()->create(['name' => 'إدارة الطلاب', 'slug' => 'students']);
        $reports = Permission::query()->create(['name' => 'عرض التقارير', 'slug' => 'reports']);
        $secretary = Role::query()->create(['name' => 'سكرتير', 'slug' => 'secretary']);
        $secretary->permissions()->sync([$dashboard->id, $students->id]);
        $user = User::factory()->create(['email' => 'secretary-update@example.test']);
        $user->roles()->attach($secretary);

        $this->put(route('users.update', $user), [
            'name' => 'سكرتير بعد التعديل',
            'email' => $user->email,
            'phone' => '01011111111',
            'job_title' => 'سكرتير',
            'is_active' => '1',
            'password' => 'UpdatedPass-2026!',
            'password_confirmation' => 'UpdatedPass-2026!',
            'permission_ids' => [$dashboard->id, $reports->id],
        ])->assertRedirect(route('users.index'));

        $user->refresh()->load(['roles.permissions', 'permissions']);

        $this->assertTrue(Hash::check('UpdatedPass-2026!', $user->password));
        $this->assertTrue($user->hasPermission('dashboard'));
        $this->assertFalse($user->hasPermission('students'));
        $this->assertTrue($user->hasPermission('reports'));
        $this->assertDatabaseHas('permission_user', ['user_id' => $user->id, 'permission_id' => $students->id, 'is_granted' => false]);
    }

    public function test_administrator_can_delete_an_unlinked_user_but_not_the_current_or_audited_user(): void
    {
        $unlinkedUser = User::factory()->create();

        $this->delete(route('users.destroy', $unlinkedUser))
            ->assertRedirect(route('users.index'));
        $this->assertModelMissing($unlinkedUser);

        $currentUser = User::query()->firstOrFail();
        $this->from(route('users.index'))
            ->delete(route('users.destroy', $currentUser))
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors('user');

        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $auditedUser = User::factory()->create();
        DailyCashMovement::query()->create([
            'academic_year_id' => $year->id,
            'type' => 'income',
            'category' => 'daily_collection',
            'amount' => 100,
            'movement_date' => '2026-09-12',
            'collector_id' => $auditedUser->id,
            'recorded_by' => $currentUser->id,
        ]);

        $this->from(route('users.index'))
            ->delete(route('users.destroy', $auditedUser))
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors('user');
        $this->assertModelExists($auditedUser);
    }

    public function test_creating_teacher_user_creates_linked_teacher_profile(): void
    {
        $teacherRole = Role::query()->create(['name' => 'مدرس', 'slug' => 'teacher']);

        $this->post('/users', [
            'name' => 'أ. أحمد سامي',
            'email' => 'ahmed@example.test',
            'phone' => '01095225454',
            'job_title' => 'مدرس رياضيات',
            'password' => 'StrongPassword-2026',
            'password_confirmation' => 'StrongPassword-2026',
            'role_id' => $teacherRole->id,
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'ahmed@example.test')->firstOrFail();
        $this->assertDatabaseHas('teachers', ['user_id' => $user->id, 'name' => 'أ. أحمد سامي', 'phone' => '01095225454']);
    }

    public function test_administrator_can_create_subject_for_year_grade_and_teacher(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);

        $this->post('/academics/subjects', [
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'teacher_id' => $teacher->id,
            'name' => 'رياضيات',
            'fee' => 450,
        ])->assertRedirect(route('academics.index'));

        $this->assertDatabaseHas('subjects', [
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'teacher_id' => $teacher->id,
            'name' => 'رياضيات',
            'fee' => 450,
        ]);
    }

    public function test_administrator_can_delete_an_unused_subject_but_not_a_subject_with_enrollments(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $unusedSubject = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'كيمياء', 'fee' => 400, 'is_active' => true]);
        $subscribedSubject = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        $student = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'سارة محمد', 'phone' => '01095225454']);
        Enrollment::query()->create(['student_id' => $student->id, 'subject_id' => $subscribedSubject->id, 'fee' => 450, 'discount_amount' => 0]);

        $this->delete(route('academics.subjects.destroy', $unusedSubject))
            ->assertRedirect(route('academics.index'));
        $this->assertModelMissing($unusedSubject);

        $this->delete(route('academics.subjects.destroy', $subscribedSubject))
            ->assertRedirect(route('academics.index'))
            ->assertSessionHasErrors('subject');
        $this->assertModelExists($subscribedSubject);
    }

    public function test_administrator_can_create_teacher(): void
    {
        $this->post('/teachers', [
            'name' => 'أ. سارة نادر',
            'phone' => '01095225454',
        ])->assertRedirect(route('teachers.index'));

        $this->assertDatabaseHas('teachers', [
            'name' => 'أ. سارة نادر',
            'phone' => '01095225454',
            'is_active' => true,
        ]);
    }

    public function test_administrator_can_create_active_year_and_grade(): void
    {
        $administrator = User::query()->firstOrFail();
        $oldYear = AcademicYear::query()->create([
            'name' => '2025 / 2026',
            'starts_on' => '2025-09-01',
            'ends_on' => '2026-06-30',
            'is_active' => false,
            'financial_closed_at' => now(),
            'financial_closed_by' => $administrator->id,
            'academic_closed_at' => now(),
            'academic_closed_by' => $administrator->id,
        ]);

        $this->post('/academics/years', [
            'name' => '2026 / 2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_active' => '1',
        ])->assertRedirect(route('academics.index'));

        $this->post('/academics/grades', [
            'name' => 'الصف الثالث الثانوي',
            'sort_order' => 3,
        ])->assertRedirect(route('academics.index'));

        $this->assertFalse($oldYear->fresh()->is_active);
        $this->assertDatabaseHas('academic_years', ['name' => '2026 / 2027', 'is_active' => true]);
        $this->assertDatabaseHas('grades', ['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
    }

    public function test_closed_year_is_financially_isolated_and_allows_the_same_phone_in_a_new_year(): void
    {
        $oldYear = AcademicYear::query()->create([
            'name' => '2025 / 2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-06-30', 'is_active' => true,
        ]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $oldSubject = Subject::query()->create([
            'academic_year_id' => $oldYear->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id,
            'name' => 'رياضيات', 'fee' => 450, 'is_active' => true,
        ]);

        $this->post('/subscriptions', [
            'student_name' => 'سارة محمد',
            'student_phone' => '01095225454',
            'grade_id' => $grade->id,
            'subjects' => [[
                'grade_id' => $grade->id,
                'subject_id' => $oldSubject->id,
                'paid_amount' => 450,
                'payment_method' => 'cash',
            ]],
        ])->assertRedirect();

        $this->post(route('academics.years.close', $oldYear), ['scope' => 'financial'])
            ->assertRedirect(route('academics.index'));
        $this->post(route('academics.years.close', $oldYear), ['scope' => 'academic'])
            ->assertRedirect(route('academics.index'));

        $this->assertNotNull($oldYear->fresh()->financial_closed_at);
        $this->assertNotNull($oldYear->fresh()->academic_closed_at);
        $this->assertFalse($oldYear->fresh()->is_active);

        $this->post('/academics/years', [
            'name' => '2026 / 2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_active' => '1',
        ])->assertRedirect(route('academics.index'));

        $newYear = AcademicYear::query()->where('is_active', true)->sole();
        $newSubject = Subject::query()->create([
            'academic_year_id' => $newYear->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id,
            'name' => 'رياضيات', 'fee' => 300, 'is_active' => true,
        ]);

        $this->post('/subscriptions', [
            'student_name' => 'سارة محمد',
            'student_phone' => '01095225454',
            'grade_id' => $grade->id,
            'subjects' => [[
                'grade_id' => $grade->id,
                'subject_id' => $newSubject->id,
                'paid_amount' => 200,
                'payment_method' => 'cash',
            ]],
        ])->assertRedirect();

        $this->assertSame(2, Student::query()->where('phone', '01095225454')->count());
        $this->assertDatabaseHas('students', ['academic_year_id' => $oldYear->id, 'phone' => '01095225454']);
        $this->assertDatabaseHas('students', ['academic_year_id' => $newYear->id, 'phone' => '01095225454']);

        $this->get(route('reports.index', ['academic_year_id' => $newYear->id, 'from' => now()->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()
            ->assertViewHas('collectionTotal', 200.0);
    }

    public function test_financial_closure_is_blocked_when_a_student_still_has_a_balance(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $subject = Subject::query()->create([
            'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id,
            'name' => 'رياضيات', 'fee' => 450, 'is_active' => true,
        ]);
        $student = Student::query()->create([
            'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'سارة محمد', 'phone' => '01095225454',
        ]);
        Enrollment::query()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'fee' => 450]);

        $this->from(route('academics.index'))
            ->post(route('academics.years.close', $year), ['scope' => 'financial'])
            ->assertRedirect(route('academics.index'))
            ->assertSessionHasErrors('scope');

        $this->assertNull($year->fresh()->financial_closed_at);
    }

    public function test_closed_year_blocks_subject_changes_even_if_its_route_is_requested_directly(): void
    {
        $administrator = User::query()->firstOrFail();
        $year = AcademicYear::query()->create([
            'name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => false,
            'financial_closed_at' => now(), 'financial_closed_by' => $administrator->id,
            'academic_closed_at' => now(), 'academic_closed_by' => $administrator->id,
        ]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $subject = Subject::query()->create([
            'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true,
        ]);

        $this->from(route('academics.index'))
            ->put(route('academics.subjects.update', $subject), ['name' => 'رياضيات متقدمة', 'fee' => 500])
            ->assertRedirect(route('academics.index'))
            ->assertSessionHasErrors('academic_year');

        $this->assertSame('رياضيات', $subject->fresh()->name);
    }

    public function test_subscription_persists_student_enrollment_and_payment(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $subject = Subject::query()->create([
            'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id,
            'name' => 'رياضيات', 'fee' => 450, 'is_active' => true,
        ]);

        $this->post('/subscriptions', [
            'student_name' => 'سارة محمد',
            'student_phone' => '01095225454',
            'grade_id' => $grade->id,
            'subjects' => [[
                'grade_id' => $grade->id,
                'subject_id' => $subject->id,
                'paid_amount' => 200,
                'payment_method' => 'cash',
            ]],
        ])->assertRedirect();

        $enrollment = Enrollment::query()->firstOrFail();
        $this->assertDatabaseHas('students', ['name' => 'سارة محمد', 'phone' => '01095225454']);
        $this->assertDatabaseHas('payments', ['enrollment_id' => $enrollment->id, 'amount' => 200, 'method' => 'cash']);
    }

    public function test_subscription_allows_a_subject_from_a_different_grade_than_the_students_profile(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $selectedGrade = Grade::query()->create(['name' => 'الصف الثاني الثانوي', 'sort_order' => 2]);
        $subjectGrade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $subject = Subject::query()->create([
            'academic_year_id' => $year->id, 'grade_id' => $subjectGrade->id, 'teacher_id' => $teacher->id,
            'name' => 'رياضيات', 'fee' => 450, 'is_active' => true,
        ]);

        $this->from('/subscriptions/create')->post('/subscriptions', [
            'student_name' => 'سارة محمد',
            'student_phone' => '01095225454',
            'grade_id' => $selectedGrade->id,
            'subjects' => [[
                'grade_id' => $subjectGrade->id,
                'subject_id' => $subject->id,
                'paid_amount' => 200,
                'payment_method' => 'cash',
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('students', ['phone' => '01095225454', 'grade_id' => $selectedGrade->id]);
        $this->assertDatabaseHas('enrollments', ['subject_id' => $subject->id]);
    }

    public function test_subscription_saves_multiple_subjects_in_one_transaction(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $math = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        $physics = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'فيزياء', 'fee' => 500, 'is_active' => true]);

        $this->post('/subscriptions', [
            'student_name' => 'سارة محمد',
            'student_phone' => '01095225454',
            'grade_id' => $grade->id,
            'subjects' => [
                ['grade_id' => $grade->id, 'subject_id' => $math->id, 'paid_amount' => 200, 'payment_method' => 'cash'],
                ['grade_id' => $grade->id, 'subject_id' => $physics->id, 'paid_amount' => 300, 'payment_method' => 'wallet'],
            ],
        ])->assertRedirect();

        $student = Student::query()->where('phone', '01095225454')->firstOrFail();
        $this->assertSame(2, Enrollment::query()->where('student_id', $student->id)->count());
        $this->assertDatabaseHas('payments', ['student_id' => $student->id, 'amount' => 200, 'method' => 'cash']);
        $this->assertDatabaseHas('payments', ['student_id' => $student->id, 'amount' => 300, 'method' => 'wallet']);
    }

    public function test_multi_subject_subscription_rolls_back_when_any_payment_exceeds_its_balance(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $math = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        $physics = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'فيزياء', 'fee' => 500, 'is_active' => true]);

        $this->from('/subscriptions/create')->post('/subscriptions', [
            'student_name' => 'سارة محمد',
            'student_phone' => '01095225454',
            'grade_id' => $grade->id,
            'subjects' => [
                ['grade_id' => $grade->id, 'subject_id' => $math->id, 'paid_amount' => 200, 'payment_method' => 'cash'],
                ['grade_id' => $grade->id, 'subject_id' => $physics->id, 'paid_amount' => 600, 'payment_method' => 'cash'],
            ],
        ])->assertRedirect('/subscriptions/create')->assertSessionHasErrors('subjects');

        $this->assertDatabaseMissing('students', ['phone' => '01095225454']);
        $this->assertSame(0, Enrollment::query()->count());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_discount_is_saved_and_updates_the_enrollment_balance(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $subject = Subject::query()->create([
            'academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id,
            'name' => 'رياضيات', 'fee' => 500, 'is_active' => true,
        ]);
        $student = Student::query()->create([
            'academic_year_id' => $year->id, 'grade_id' => $grade->id,
            'name' => 'سارة محمد', 'phone' => '01095225454',
        ]);
        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id, 'subject_id' => $subject->id, 'fee' => 500, 'discount_amount' => 0,
        ]);

        $this->post('/discounts', [
            'enrollment_id' => $enrollment->id,
            'type' => 'percentage',
            'value' => 10,
            'reason' => 'خصم أخوة',
        ])->assertRedirect(route('discounts.index'));

        $this->assertDatabaseHas('discounts', [
            'enrollment_id' => $enrollment->id, 'type' => 'percentage', 'amount' => 50, 'reason' => 'خصم أخوة',
        ]);
        $this->assertSame('50.00', $enrollment->fresh()->discount_amount);
        $this->assertSame(1, Discount::query()->count());
    }

    public function test_teacher_payout_is_persisted_with_its_date_and_executor(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي', 'is_active' => true]);

        $this->post('/teacher-payouts', [
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'amount' => 1250,
            'payout_date' => '2026-09-10',
            'method' => 'transfer',
            'note' => 'مستحقات الأسبوع الأول',
        ])->assertRedirect(route('teacher-payouts.index'));

        $this->assertDatabaseHas('teacher_payouts', [
            'teacher_id' => $teacher->id,
            'amount' => 1250,
            'method' => 'transfer',
            'note' => 'مستحقات الأسبوع الأول',
        ]);

        $payout = TeacherPayout::query()->sole();

        $this->assertSame('2026-09-10', $payout->period_from->toDateString());
        $this->assertSame('2026-09-10', $payout->period_to->toDateString());
        $this->assertSame('2026-09-10', $payout->paid_at->toDateString());
    }

    public function test_teacher_payout_cannot_use_a_subject_assigned_to_another_teacher(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $firstTeacher = Teacher::query()->create(['name' => 'أ. أحمد سامي', 'is_active' => true]);
        $secondTeacher = Teacher::query()->create(['name' => 'أ. سارة نادر', 'is_active' => true]);
        $secondTeachersSubject = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $secondTeacher->id, 'name' => 'لغة إنجليزية', 'fee' => 350, 'is_active' => true]);

        $this->from(route('teacher-payouts.index'))->post(route('teacher-payouts.store'), [
            'teacher_id' => $firstTeacher->id,
            'subject_id' => $secondTeachersSubject->id,
            'amount' => 100,
            'payout_date' => '2026-09-12',
            'method' => 'cash',
        ])->assertRedirect(route('teacher-payouts.index'))
            ->assertSessionHasErrors('subject_id');

        $this->assertDatabaseCount('teacher_payouts', 0);
    }

    public function test_secretary_can_record_daily_center_income_and_expense_without_affecting_student_payments(): void
    {
        $year = AcademicYear::query()->create([
            'name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true,
        ]);
        $recorder = User::query()->firstOrFail();
        $anotherUser = User::factory()->create();

        $this->post(route('daily-cashbook.store'), [
            'type' => 'income',
            'category' => 'daily_collection',
            'amount' => 1850,
            'movement_date' => '2026-09-12',
            // The submitted value is ignored: the signed-in user is always the audit collector.
            'collector_id' => $anotherUser->id,
            'note' => 'تحصيل الحصة المسائية',
        ])->assertRedirect(route('daily-cashbook.index', ['from' => '2026-09-12', 'to' => '2026-09-12']));

        $this->post(route('daily-cashbook.store'), [
            'type' => 'expense',
            'category' => 'electricity',
            'amount' => 300,
            'movement_date' => '2026-09-12',
            'note' => 'فاتورة الكهرباء',
        ])->assertRedirect(route('daily-cashbook.index', ['from' => '2026-09-12', 'to' => '2026-09-12']));

        $this->assertDatabaseHas('daily_cash_movements', ['academic_year_id' => $year->id, 'type' => 'income', 'category' => 'daily_collection', 'amount' => 1850, 'collector_id' => $recorder->id, 'recorded_by' => $recorder->id]);
        $this->assertDatabaseHas('daily_cash_movements', ['type' => 'expense', 'category' => 'electricity', 'amount' => 300]);
        $this->assertSame(2, DailyCashMovement::query()->count());
        $this->assertSame(0, Payment::query()->count());

        $this->get(route('daily-cashbook.index', ['date' => '2026-09-12']))
            ->assertOk()
            ->assertSee('إيراد ومصروف اليوم')
            ->assertSee('تحصيل الحصة المسائية')
            ->assertSee('فاتورة الكهرباء');

        $this->get('/reports?from=2026-09-12&to=2026-09-12&type=daily_expense')
            ->assertOk()
            ->assertSee('فاتورة الكهرباء')
            ->assertDontSee('تحصيل الحصة المسائية');
    }

    public function test_reports_filter_the_audit_log_by_movement_type(): void
    {
        $user = User::query()->firstOrFail();
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $subject = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        $student = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'سارة محمد', 'phone' => '01095225454']);
        $enrollment = Enrollment::query()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'fee' => 450, 'discount_amount' => 0]);
        Payment::query()->create(['student_id' => $student->id, 'enrollment_id' => $enrollment->id, 'received_by' => $user->id, 'amount' => 250, 'method' => 'cash', 'receipt_number' => 'TEST-PAYMENT-001', 'paid_at' => now()]);
        TeacherPayout::query()->create(['academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'paid_by' => $user->id, 'amount' => 100, 'period_from' => now()->startOfMonth(), 'period_to' => now(), 'method' => 'cash', 'note' => 'مستحقات اختبارية', 'paid_at' => now()]);

        $this->get('/reports?type=collection')
            ->assertOk()
            ->assertSee('سارة محمد')
            ->assertDontSee('مستحقات اختبارية');

        $this->get('/reports?type=payout')
            ->assertOk()
            ->assertSee('مستحقات اختبارية')
            ->assertDontSee('سارة محمد');
    }

    public function test_collection_submission_token_prevents_duplicate_payment_when_a_form_is_resubmitted(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $subject = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        $student = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'سارة محمد', 'phone' => '01095225454']);
        $enrollment = Enrollment::query()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'fee' => 450, 'discount_amount' => 0]);
        $payload = ['enrollment_id' => $enrollment->id, 'amount' => 200, 'method' => 'cash', 'size' => 'A5', 'submission_token' => '4f1c9c25-7c1f-4a98-81e4-537a82151820'];

        $this->post('/collections', $payload)->assertRedirect();
        $this->post('/collections', $payload)->assertRedirect();

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame('200.00', (string) Payment::query()->firstOrFail()->amount);
    }

    public function test_student_subject_can_be_added_from_the_student_profile_with_initial_payment_and_discount(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $subject = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'فيزياء', 'fee' => 500, 'is_active' => true]);
        $student = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'سارة محمد', 'phone' => '01095225454']);

        $this->post(route('students.subjects.store', $student), ['subject_id' => $subject->id, 'paid_amount' => 200, 'payment_method' => 'cash', 'discount_type' => 'amount', 'discount_value' => 50, 'reason' => 'خصم تجريبي'])
            ->assertRedirect();

        $enrollment = Enrollment::query()->firstOrFail();
        $this->assertSame('50.00', $enrollment->discount_amount);
        $this->assertDatabaseHas('payments', ['enrollment_id' => $enrollment->id, 'amount' => 200]);
        $this->assertDatabaseHas('discounts', ['enrollment_id' => $enrollment->id, 'amount' => 50]);
    }

    public function test_cancelling_an_enrollment_records_a_refund_in_the_financial_report(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي']);
        $subject = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        $student = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'سارة محمد', 'phone' => '01095225454']);
        $enrollment = Enrollment::query()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'fee' => 450, 'discount_amount' => 0]);
        Payment::query()->create(['student_id' => $student->id, 'enrollment_id' => $enrollment->id, 'received_by' => User::query()->firstOrFail()->id, 'amount' => 300, 'method' => 'cash', 'receipt_number' => 'REFUND-TEST-001', 'paid_at' => now()]);

        $this->post(route('enrollments.cancel', $enrollment), ['refund_amount' => 100, 'refund_method' => 'cash', 'refund_note' => 'اختبار رد'])
            ->assertRedirect();

        $this->assertDatabaseHas('refunds', ['enrollment_id' => $enrollment->id, 'amount' => 100, 'method' => 'cash']);
        $this->get('/reports?type=refund')->assertOk()->assertSee('رد مبلغ طالب')->assertSee('سارة محمد');
    }

    public function test_administrator_can_edit_and_deactivate_teacher_subject_and_user_without_deleting_records(): void
    {
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. أحمد سامي', 'phone' => '01000000000', 'is_active' => true]);
        $subject = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        $user = User::factory()->create(['is_active' => true]);

        $this->put(route('teachers.update', $teacher), ['name' => 'أ. أحمد سامي المعدل', 'phone' => '01000000000'])->assertRedirect(route('teachers.index'));
        $this->put(route('academics.subjects.update', $subject), ['name' => 'رياضيات متقدمة', 'teacher_id' => $teacher->id, 'fee' => 500])->assertRedirect(route('academics.index'));
        $this->put(route('users.update', $user), ['name' => 'مستخدم موقوف', 'email' => $user->email, 'phone' => '01011111111', 'job_title' => 'سكرتير'])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'name' => 'أ. أحمد سامي المعدل', 'is_active' => false]);
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'رياضيات متقدمة', 'fee' => 500, 'is_active' => false]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'مستخدم موقوف', 'is_active' => false]);
    }

    public function test_reports_support_teacher_and_date_filters_with_preview_and_whatsapp_recipient_selection(): void
    {
        $recipient = User::query()->firstOrFail();
        $recipient->update(['name' => 'مدير الاختبار', 'phone' => '01012345678']);
        $year = AcademicYear::query()->create(['name' => '2026 / 2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $grade = Grade::query()->create(['name' => 'الصف الثالث الثانوي', 'sort_order' => 3]);
        $teacher = Teacher::query()->create(['name' => 'أ. مدرس سابق', 'phone' => '01098765432', 'is_active' => false]);
        $subject = Subject::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'teacher_id' => $teacher->id, 'name' => 'رياضيات', 'fee' => 450, 'is_active' => true]);
        $student = Student::query()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'طالب الفترة', 'phone' => '01055555555']);
        $enrollment = Enrollment::query()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'fee' => 450, 'discount_amount' => 0]);
        Payment::query()->create(['student_id' => $student->id, 'enrollment_id' => $enrollment->id, 'received_by' => $recipient->id, 'amount' => 250, 'method' => 'cash', 'receipt_number' => 'REPORT-FILTER-001', 'paid_at' => '2026-09-10 12:00:00']);
        $filters = ['academic_year_id' => $year->id, 'from' => '2026-09-10', 'to' => '2026-09-10', 'teacher_id' => $teacher->id, 'type' => 'collection'];

        $this->get(route('reports.index', $filters))
            ->assertOk()
            ->assertSee('أ. مدرس سابق — موقوف')
            ->assertSee('طالب الفترة')
            ->assertSee('2026-09-10');

        $this->get(route('reports.preview', $filters))
            ->assertOk()
            ->assertSee('طباعة أو حفظ PDF')
            ->assertSee('طالب الفترة');

        $this->get(route('reports.whatsapp', $filters))
            ->assertOk()
            ->assertSee('مدير الاختبار')
            ->assertSee('فتح واتساب لإرفاق المستند')
            ->assertSee('رقم واتساب آخر');

        $response = $this->get(route('reports.whatsapp.redirect', [...$filters, 'recipient_type' => 'user', 'recipient_id' => $recipient->id]));
        $response->assertRedirect();
        $this->assertSame('https://wa.me/201012345678', (string) $response->headers->get('Location'));

        $this->get(route('reports.whatsapp.redirect', [...$filters, 'recipient_type' => 'custom', 'recipient_phone' => '+201055555555']))
            ->assertRedirect('https://wa.me/201055555555');
    }
}

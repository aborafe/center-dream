<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Student;
use App\Models\TeacherPayout;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_a_complete_financial_workspace(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(5, Student::query()->count());
        $this->assertSame(6, Enrollment::query()->count());
        $this->assertSame(6, Payment::query()->count());
        $this->assertSame(1, Discount::query()->count());
        $this->assertSame(1, TeacherPayout::query()->count());
        $this->assertSame(1, Refund::query()->count());
        $this->assertDatabaseHas('enrollments', ['refund_amount' => 100]);
        $this->assertDatabaseHas('students', ['phone' => '01095225454', 'name' => 'سارة محمد']);
    }
}

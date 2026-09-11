<?php

namespace App\Actions;

use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddStudentSubject
{
    /** @param array{subject_id: int, paid_amount: numeric-string|int|float, payment_method: string, discount_type?: string|null, discount_value?: numeric-string|int|float|null, reason?: string|null} $data */
    public function handle(Student $student, array $data, User $user): Enrollment
    {
        return DB::transaction(function () use ($student, $data, $user): Enrollment {
            $subject = Subject::query()->lockForUpdate()->findOrFail($data['subject_id']);

            if (! $subject->is_active || $subject->academic_year_id !== $student->academic_year_id || $subject->grade_id !== $student->grade_id) {
                throw ValidationException::withMessages(['subject_id' => 'هذه المادة ليست متاحة لسنة وصف الطالب.']);
            }

            if (Enrollment::query()->where('student_id', $student->id)->where('subject_id', $subject->id)->exists()) {
                throw ValidationException::withMessages(['subject_id' => 'الطالب مسجل بالفعل في هذه المادة.']);
            }

            $discountType = $data['discount_type'] ?? null;
            $discountValue = (float) ($data['discount_value'] ?? 0);
            if ($discountType === 'percentage' && $discountValue > 100) {
                throw ValidationException::withMessages(['discount_value' => 'نسبة الخصم لا تتجاوز 100%.']);
            }
            $discountAmount = $discountType === 'percentage' ? round((float) $subject->fee * $discountValue / 100, 2) : $discountValue;
            if ($discountAmount < 0 || $discountAmount > (float) $subject->fee) {
                throw ValidationException::withMessages(['discount_value' => 'قيمة الخصم أكبر من رسوم المادة.']);
            }

            $paidAmount = (float) $data['paid_amount'];
            if ($paidAmount > (float) $subject->fee - $discountAmount) {
                throw ValidationException::withMessages(['paid_amount' => 'المدفوع أكبر من الرصيد بعد الخصم.']);
            }

            $enrollment = Enrollment::query()->create(['student_id' => $student->id, 'subject_id' => $subject->id, 'fee' => $subject->fee, 'discount_amount' => $discountAmount]);

            if ($discountAmount > 0) {
                Discount::query()->create(['enrollment_id' => $enrollment->id, 'approved_by' => $user->id, 'type' => $discountType, 'value' => $discountValue, 'amount' => $discountAmount, 'reason' => $data['reason'] ?: 'خصم عند إضافة المادة']);
            }

            if ($paidAmount > 0) {
                $now = now();
                $enrollment->payments()->create(['student_id' => $student->id, 'received_by' => $user->id, 'amount' => $paidAmount, 'method' => $data['payment_method'], 'receipt_number' => 'CD-'.$now->format('ymd-His').'-'.str_pad((string) $enrollment->id, 4, '0', STR_PAD_LEFT), 'submission_token' => (string) Str::uuid(), 'paid_at' => $now]);
            }

            return $enrollment;
        });
    }
}

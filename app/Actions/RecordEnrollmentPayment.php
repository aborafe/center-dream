<?php

namespace App\Actions;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use App\Support\AcademicYearLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordEnrollmentPayment
{
    public function handle(int $enrollmentId, float $amount, string $method, User $receiver, string $submissionToken): Payment
    {
        return DB::transaction(function () use ($enrollmentId, $amount, $method, $receiver, $submissionToken): Payment {
            $existing = Payment::query()->where('submission_token', $submissionToken)->first();
            if ($existing) {
                return $existing;
            }
            $enrollment = Enrollment::query()->with(['payments', 'subject.academicYear'])->lockForUpdate()->findOrFail($enrollmentId);
            AcademicYearLedger::ensureOpen($enrollment->subject->academicYear);
            if ($enrollment->cancelled_at) {
                throw ValidationException::withMessages(['enrollment_id' => 'لا يمكن تحصيل دفعة من اشتراك ملغى.']);
            }
            $remaining = (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount');

            if ($amount > $remaining) {
                throw ValidationException::withMessages(['amount' => 'المبلغ أكبر من الرصيد المتبقي لهذه المادة.']);
            }

            return Payment::query()->create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'received_by' => $receiver->id,
                'amount' => $amount,
                'method' => $method,
                'receipt_number' => 'CD-'.now()->format('ymd-His').'-'.str_pad((string) $enrollment->id, 4, '0', STR_PAD_LEFT),
                'submission_token' => $submissionToken,
                'paid_at' => now(),
            ]);
        });
    }
}

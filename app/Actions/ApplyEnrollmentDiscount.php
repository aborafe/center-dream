<?php

namespace App\Actions;

use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\AcademicYearLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyEnrollmentDiscount
{
    public function handle(int $enrollmentId, string $type, float $value, string $reason, User $approver): Discount
    {
        return DB::transaction(function () use ($enrollmentId, $type, $value, $reason, $approver): Discount {
            $enrollment = Enrollment::query()->with(['payments', 'subject.academicYear'])->lockForUpdate()->findOrFail($enrollmentId);
            AcademicYearLedger::ensureOpen($enrollment->subject->academicYear);

            if ($type === 'percentage' && $value > 100) {
                throw ValidationException::withMessages(['value' => 'لا يمكن أن تتجاوز نسبة الخصم 100%.']);
            }

            $amount = $type === 'percentage'
                ? round((float) $enrollment->fee * $value / 100, 2)
                : round($value, 2);
            $remaining = max(0, (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments->sum('amount'));

            if ($amount > $remaining) {
                throw ValidationException::withMessages(['value' => 'قيمة الخصم أكبر من الرصيد المتبقي.']);
            }

            $discount = Discount::query()->create([
                'enrollment_id' => $enrollment->id,
                'approved_by' => $approver->id,
                'type' => $type,
                'value' => $value,
                'amount' => $amount,
                'reason' => $reason,
            ]);
            $enrollment->increment('discount_amount', $amount);

            return $discount;
        });
    }
}

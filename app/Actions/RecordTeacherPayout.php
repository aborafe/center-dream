<?php

namespace App\Actions;

use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherPayout;
use App\Models\User;
use App\Support\AcademicYearLedger;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RecordTeacherPayout
{
    /** @param array{teacher_id: int, amount: numeric-string|int|float, payout_date: string, method: string, note?: string|null} $data */
    public function handle(array $data, User $payer): TeacherPayout
    {
        $academicYear = AcademicYearLedger::active();
        AcademicYearLedger::ensureOpen($academicYear);
        $teacher = Teacher::query()->where('is_active', true)->findOrFail($data['teacher_id']);
        if (! empty($data['subject_id']) && ! Subject::query()->whereKey($data['subject_id'])->where('teacher_id', $teacher->id)->where('academic_year_id', $academicYear->id)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'المادة المختارة لا تتبع هذا المدرس.']);
        }

        return TeacherPayout::query()->create([
            'teacher_id' => $teacher->id,
            'academic_year_id' => $academicYear->id,
            'subject_id' => $data['subject_id'] ?? null,
            'paid_by' => $payer->id,
            'amount' => $data['amount'],
            // Keep legacy columns aligned to the transaction date until a later schema cleanup.
            'period_from' => $data['payout_date'],
            'period_to' => $data['payout_date'],
            'method' => $data['method'],
            'note' => $data['note'] ?? null,
            'paid_at' => Carbon::parse($data['payout_date'])->startOfDay(),
        ]);
    }
}

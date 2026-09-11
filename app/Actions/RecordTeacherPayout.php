<?php

namespace App\Actions;

use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherPayout;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RecordTeacherPayout
{
    /** @param array{teacher_id: int, amount: numeric-string|int|float, period_from: string, period_to: string, method: string, note?: string|null} $data */
    public function handle(array $data, User $payer): TeacherPayout
    {
        $teacher = Teacher::query()->where('is_active', true)->findOrFail($data['teacher_id']);
        if (! empty($data['subject_id']) && ! Subject::query()->whereKey($data['subject_id'])->where('teacher_id', $teacher->id)->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'المادة المختارة لا تتبع هذا المدرس.']);
        }

        return TeacherPayout::query()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $data['subject_id'] ?? null,
            'paid_by' => $payer->id,
            'amount' => $data['amount'],
            'period_from' => $data['period_from'],
            'period_to' => $data['period_to'],
            'method' => $data['method'],
            'note' => $data['note'] ?? null,
            'paid_at' => now(),
        ]);
    }
}

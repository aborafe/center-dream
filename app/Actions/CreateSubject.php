<?php

namespace App\Actions;

use App\Models\AcademicYear;
use App\Models\Subject;
use App\Support\AcademicYearLedger;
use Illuminate\Validation\ValidationException;

class CreateSubject
{
    /** @param array{academic_year_id: int, grade_id: int, teacher_id?: int|null, name: string, fee: numeric-string|int|float} $data */
    public function handle(array $data): Subject
    {
        $academicYear = AcademicYear::query()->findOrFail($data['academic_year_id']);
        AcademicYearLedger::ensureOpen($academicYear);

        if (Subject::query()->where('academic_year_id', $data['academic_year_id'])->where('grade_id', $data['grade_id'])->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['name' => 'هذه المادة موجودة بالفعل لنفس السنة والصف.']);
        }

        return Subject::query()->create([...$data, 'is_active' => true]);
    }
}

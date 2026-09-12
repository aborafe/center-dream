<?php

namespace App\Actions;

use App\Models\DailyCashMovement;
use App\Models\User;
use App\Support\AcademicYearLedger;

class RecordDailyCashMovement
{
    /** @param array{type: string, category: string, amount: numeric-string|int|float, movement_date: string, note?: string|null} $data */
    public function handle(array $data, User $recorder): DailyCashMovement
    {
        $academicYear = AcademicYearLedger::active();
        AcademicYearLedger::ensureOpen($academicYear);

        return DailyCashMovement::query()->create([
            'academic_year_id' => $academicYear->id,
            'type' => $data['type'],
            'category' => $data['category'],
            'amount' => $data['amount'],
            'movement_date' => $data['movement_date'],
            // Income belongs to the center. The signed-in user is saved only as the audit trail.
            'collector_id' => $data['type'] === 'income' ? $recorder->id : null,
            'recorded_by' => $recorder->id,
            'note' => $data['note'] ?? null,
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCashMovement extends Model
{
    protected $fillable = ['academic_year_id', 'type', 'category', 'amount', 'movement_date', 'collector_id', 'recorded_by', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'movement_date' => 'date'];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

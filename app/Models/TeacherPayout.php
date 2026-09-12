<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherPayout extends Model
{
    protected $fillable = ['teacher_id', 'academic_year_id', 'subject_id', 'paid_by', 'amount', 'period_from', 'period_to', 'method', 'note', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'period_from' => 'date', 'period_to' => 'date', 'paid_at' => 'datetime'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}

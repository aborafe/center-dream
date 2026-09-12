<?php

namespace App\Support;

use App\Models\AcademicYear;
use Illuminate\Validation\ValidationException;

class AcademicYearLedger
{
    public static function active(): AcademicYear
    {
        $year = AcademicYear::query()->where('is_active', true)->first();

        if (! $year) {
            throw ValidationException::withMessages(['academic_year' => 'لا توجد سنة دراسية نشطة. أضف سنة جديدة وحددها كنشطة أولًا.']);
        }

        return $year;
    }

    public static function ensureOpen(AcademicYear $year): void
    {
        if (! $year->is_active || $year->academic_closed_at) {
            throw ValidationException::withMessages(['academic_year' => 'السنة الدراسية مغلقة ولا يمكن تسجيل عمليات جديدة بها.']);
        }

        if ($year->financial_closed_at) {
            throw ValidationException::withMessages(['academic_year' => 'الفترة المالية لهذه السنة مقفلة ولا يمكن تعديل دفترها.']);
        }
    }
}

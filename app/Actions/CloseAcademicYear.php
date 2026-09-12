<?php

namespace App\Actions;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseAcademicYear
{
    public function handle(AcademicYear $year, string $scope, User $user): void
    {
        if (! $year->is_active || $year->academic_closed_at) {
            throw ValidationException::withMessages(['scope' => 'هذه السنة الدراسية مغلقة بالفعل ولا يمكن تغيير حالة دفاترها.']);
        }

        if ($scope === 'financial') {
            if ($year->financial_closed_at) {
                throw ValidationException::withMessages(['scope' => 'الفترة المالية لهذه السنة مقفلة بالفعل.']);
            }

            $hasDue = DB::table('enrollments')
                ->join('subjects', 'subjects.id', '=', 'enrollments.subject_id')
                ->leftJoin('payments', 'payments.enrollment_id', '=', 'enrollments.id')
                ->where('subjects.academic_year_id', $year->id)
                ->whereNull('enrollments.cancelled_at')
                ->groupBy('enrollments.id', 'enrollments.fee', 'enrollments.discount_amount')
                ->havingRaw('enrollments.fee - enrollments.discount_amount - COALESCE(SUM(payments.amount), 0) > 0')
                ->exists();

            if ($hasDue) {
                throw ValidationException::withMessages(['scope' => 'لا يمكن قفل الفترة المالية قبل تسوية أرصدة الطلاب أو إلغاء الاشتراكات المتبقية.']);
            }

            $year->update(['financial_closed_at' => now(), 'financial_closed_by' => $user->id]);

            return;
        }

        if (! $year->financial_closed_at) {
            throw ValidationException::withMessages(['scope' => 'أقفل الفترة المالية بعد تسوية الأرصدة قبل قفل السنة الدراسية.']);
        }

        $year->update(['academic_closed_at' => now(), 'academic_closed_by' => $user->id, 'is_active' => false]);
    }
}

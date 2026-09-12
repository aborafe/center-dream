<?php

namespace App\Actions;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\AcademicYearLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEnrollmentWithPayment
{
    /** @param array{student_name: string, student_phone: string, grade_id: int, subjects: list<array{grade_id: int, subject_id: int, paid_amount: numeric-string|int|float, payment_method: string}>} $data */
    public function handle(array $data, User $receiver): Collection
    {
        return DB::transaction(function () use ($data, $receiver): Collection {
            $subjects = Subject::query()->whereKey(collect($data['subjects'])->pluck('subject_id'))->get()->keyBy('id');
            $academicYear = AcademicYearLedger::active();
            AcademicYearLedger::ensureOpen($academicYear);

            if ($subjects->isEmpty() || $subjects->count() !== count($data['subjects']) || $subjects->contains(fn (Subject $subject): bool => $subject->academic_year_id !== $academicYear->id) || collect($data['subjects'])->contains(fn (array $item): bool => $subjects->get($item['subject_id'])?->grade_id !== (int) $item['grade_id'])) {
                throw ValidationException::withMessages(['subjects' => 'يجب أن تتطابق كل مادة مع صف المادة المختار وأن تكون من السنة الدراسية النشطة.']);
            }

            $phone = $this->normalizeEgyptianPhone($data['student_phone']);

            $student = Student::query()->firstOrCreate(
                ['academic_year_id' => $academicYear->id, 'phone' => $phone],
                [
                    'name' => $data['student_name'],
                    'academic_year_id' => $academicYear->id,
                    'grade_id' => $data['grade_id'],
                ],
            );

            return collect($data['subjects'])->map(function (array $item) use ($student, $subjects, $receiver): Enrollment {
                $subject = $subjects->get($item['subject_id']);
                $enrollment = Enrollment::query()->firstOrCreate(
                    ['student_id' => $student->id, 'subject_id' => $subject->id],
                    ['fee' => $subject->fee, 'discount_amount' => 0],
                );
                $paidAmount = (float) $item['paid_amount'];
                $remaining = (float) $enrollment->fee - (float) $enrollment->discount_amount - (float) $enrollment->payments()->sum('amount');

                if ($paidAmount > $remaining) {
                    throw ValidationException::withMessages(['subjects' => "المبلغ المدفوع أكبر من الرصيد المتبقي لمادة {$subject->name}."]);
                }

                if ($paidAmount > 0) {
                    Payment::query()->create([
                        'student_id' => $student->id,
                        'enrollment_id' => $enrollment->id,
                        'received_by' => $receiver->id,
                        'amount' => $paidAmount,
                        'method' => $item['payment_method'],
                        'receipt_number' => 'CD-'.now()->format('ymd-His').'-'.str_pad((string) $enrollment->id, 4, '0', STR_PAD_LEFT),
                        'paid_at' => now(),
                    ]);
                }

                return $enrollment;
            });
        });
    }

    private function normalizeEgyptianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '20')) {
            $digits = substr($digits, 2);
        }

        return str_starts_with($digits, '0') ? $digits : '0'.$digits;
    }
}

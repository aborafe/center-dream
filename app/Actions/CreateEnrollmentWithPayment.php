<?php

namespace App\Actions;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEnrollmentWithPayment
{
    /** @param array{student_name: string, student_phone: string, subjects: list<array{subject_id: int, paid_amount: numeric-string|int|float, payment_method: string}>} $data */
    public function handle(array $data, User $receiver): Collection
    {
        return DB::transaction(function () use ($data, $receiver): Collection {
            $subjects = Subject::query()->whereKey(collect($data['subjects'])->pluck('subject_id'))->get()->keyBy('id');
            $firstSubject = $subjects->firstOrFail();

            if ($subjects->count() !== count($data['subjects']) || $subjects->contains(fn (Subject $subject): bool => $subject->academic_year_id !== $firstSubject->academic_year_id || $subject->grade_id !== $firstSubject->grade_id)) {
                throw ValidationException::withMessages(['subjects' => 'اختر موادًا من نفس السنة الدراسية والصف.']);
            }

            $phone = $this->normalizeEgyptianPhone($data['student_phone']);

            $student = Student::query()->firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => $data['student_name'],
                    'academic_year_id' => $firstSubject->academic_year_id,
                    'grade_id' => $firstSubject->grade_id,
                ],
            );

            if ($student->academic_year_id !== $firstSubject->academic_year_id || $student->grade_id !== $firstSubject->grade_id) {
                throw ValidationException::withMessages([
                    'subjects' => 'بيانات الطالب المسجلة لا تتوافق مع سنة وصف المواد المختارة.',
                ]);
            }

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

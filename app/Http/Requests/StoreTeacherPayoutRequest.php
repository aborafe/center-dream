<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payout_date' => ['required', 'date'],
            'method' => ['required', 'in:cash,transfer,wallet'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}

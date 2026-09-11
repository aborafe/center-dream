<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,transfer,wallet'],
            'size' => ['required', 'in:A4,A5'],
            'submission_token' => ['required', 'uuid'],
            'return_to_student' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'name' => ['required', 'string', 'max:120'],
            'fee' => ['required', 'numeric', 'gt:0'],
        ];
    }
}

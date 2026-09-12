<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'student_name' => ['required', 'string', 'max:120'],
            'student_phone' => ['required', 'regex:/^(?:\\+?20|0)?1[0125][0-9]{8}$/'],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.grade_id' => ['required', 'integer', 'exists:grades,id'],
            'subjects.*.subject_id' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
            'subjects.*.paid_amount' => ['required', 'numeric', 'min:0'],
            'subjects.*.payment_method' => ['required', 'in:cash,transfer,wallet'],
        ];
    }
}

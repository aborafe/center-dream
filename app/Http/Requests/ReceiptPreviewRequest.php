<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiptPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'student' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:24'],
            'subject' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'string', 'max:40'],
            'method' => ['required', 'string', 'max:40'],
            'size' => ['nullable', 'in:A4,A5'],
        ];
    }
}

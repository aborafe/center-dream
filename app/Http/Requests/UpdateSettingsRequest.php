<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'center_name' => ['required', 'string', 'max:120'],
            'center_phone' => ['required', 'string', 'max:24'],
            'address' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:32'],
            'balance_alerts' => ['nullable', 'boolean'],
            'daily_summary' => ['nullable', 'boolean'],
            'daily_report_copy' => ['nullable', 'boolean'],
        ];
    }
}

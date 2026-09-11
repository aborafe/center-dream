<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^(?:\\+?20|0)?1[0125][0-9]{8}$/', Rule::unique('users', 'phone')->ignore($this->user())],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($this->user())],
            'job_title' => ['required', 'string', 'max:120'],
            'current_password' => ['required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}

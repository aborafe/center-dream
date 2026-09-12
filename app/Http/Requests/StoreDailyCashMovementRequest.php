<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', Rule::in(['daily_collection', 'electricity', 'rent', 'owner_transfer', 'supplies', 'other'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'movement_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->input('type') === 'income' && $this->input('category') !== 'daily_collection') {
                $validator->errors()->add('category', 'إيراد اليوم يجب أن يكون تحصيلًا يوميًا.');
            }

            if ($this->input('type') === 'expense' && $this->input('category') === 'daily_collection') {
                $validator->errors()->add('category', 'اختر نوع المصروف الصحيح.');
            }
        }];
    }
}

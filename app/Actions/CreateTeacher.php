<?php

namespace App\Actions;

use App\Models\Teacher;

class CreateTeacher
{
    /** @param array{name: string, phone?: string|null} $data */
    public function handle(array $data): Teacher
    {
        return Teacher::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
        ]);
    }
}

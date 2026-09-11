<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;

class CreateCenterUser
{
    /** @param array{name: string, email: string, phone?: string|null, job_title: string, password: string, role_id: int, permission_ids?: list<int>} $data */
    public function handle(array $data): User
    {
        $role = Role::query()->findOrFail($data['role_id']);
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'],
            'password' => $data['password'],
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        if ($role->slug === 'teacher') {
            Teacher::query()->create([
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'is_active' => true,
            ]);
        }

        if ($role->slug !== 'admin') {
            $user->permissions()->sync($data['permission_ids'] ?? []);
        }

        return $user;
    }
}

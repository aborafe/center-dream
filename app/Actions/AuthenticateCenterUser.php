<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthenticateCenterUser
{
    public function __invoke(string $identifier, string $password): ?User
    {
        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if (! $user || ! $user->is_active || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }
}

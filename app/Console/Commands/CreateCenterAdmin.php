<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateCenterAdmin extends Command
{
    protected $signature = 'center:create-admin
                            {--name=}
                            {--email=}
                            {--phone=}
                            {--password=}';

    protected $description = 'Create the first active center administrator securely.';

    public function handle(): int
    {
        $details = [
            'name' => $this->option('name') ?: $this->ask('الاسم الكامل'),
            'email' => $this->option('email') ?: $this->ask('البريد الإلكتروني'),
            'phone' => $this->option('phone') ?: $this->ask('رقم الهاتف'),
            'password' => $this->option('password') ?: $this->secret('كلمة المرور'),
        ];

        $validator = Validator::make($details, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['required', 'regex:/^01[0125][0-9]{8}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $adminRole = Role::query()
            ->where('slug', 'admin')
            ->first();

        if (! $adminRole) {
            $this->error(
                'لم يتم العثور على دور مسؤول المركز. شغّل php artisan db:seed أولًا.'
            );

            return self::FAILURE;
        }

        $admin = User::query()->create([
            'name' => $details['name'],
            'email' => $details['email'],
            'phone' => $details['phone'],
            'password' => Hash::make($details['password']),
            'is_active' => true,
        ]);

        $admin->roles()->attach($adminRole);

        $this->info('تم إنشاء حساب مسؤول المركز بنجاح.');

        return self::SUCCESS;
    }
}

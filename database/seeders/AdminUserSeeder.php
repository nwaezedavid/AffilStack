<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@affilstack.test');
        $password = env('ADMIN_PASSWORD', 'change-this-password');

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'AffilStack Admin',
                'password' => Hash::make($password),
                'credits_balance' => 0,
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $this->command?->info("Admin user ready: {$email} — change the password immediately after first login.");
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'support', 'user'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}

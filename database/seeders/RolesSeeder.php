<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // 'super-admin' is deliberately separate from 'admin': the AI agents
        // (Tom/Sam/Brain/Tony) route every permission/approval and every
        // codebase change exclusively through it, so a future admin
        // sub-account (still unbuilt) never inherits that authority just by
        // being an admin.
        foreach (['super-admin', 'admin', 'support', 'user'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}

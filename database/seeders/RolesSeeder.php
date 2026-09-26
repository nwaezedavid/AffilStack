<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // 'super-admin' is deliberately separate from 'admin': the AI agents
        // (Tom/Sam/Brain/Tony) route every permission/approval and every
        // codebase change exclusively through it, so an admin sub-account
        // never inherits that authority just by being an admin.
        //
        // 'admin_sub' (admin sub-accounts) is the department-scoped staff
        // role: it grants panel login but sees only the nav
        // groups/resources covered by the "department.*" permissions
        // below, individually assigned per sub-account — never the full
        // access 'admin'/'super-admin' have. See User::canAccessDepartment()
        // and App\Filament\Concerns\ScopedToDepartment.
        foreach (['super-admin', 'admin', 'admin_sub', 'support', 'user'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        foreach (array_keys(config('admin.departments')) as $department) {
            Permission::findOrCreate("department.{$department}", 'web');
        }

        // The 'support' role can sign in to the panel but, having no
        // department of its own, could only ever see an empty dashboard —
        // give it the one department its name promises.
        Role::findByName('support', 'web')->givePermissionTo('department.support');
    }
}

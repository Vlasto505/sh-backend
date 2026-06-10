<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'content.view', 'content.create', 'content.edit', 'content.delete',
            'users.view', 'users.edit', 'users.delete',
            'calls.view', 'calls.create', 'calls.edit', 'calls.close',
            'applications.view_own', 'applications.view_all',
            'applications.evaluate', 'applications.decide', 'applications.request_supplement',
            'organizations.create', 'organizations.edit_own', 'organizations.view_all',
            'teams.create', 'teams.invite',
            'mentorship.view_own', 'mentorship.manage',
            'reports.view', 'reports.export',
            'audit.view',
            'system.configure', 'roles.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roleMap = [
            'student' => [
                'applications.view_own', 'teams.create', 'teams.invite', 'mentorship.view_own',
            ],
            'team_leader' => [
                'applications.view_own', 'teams.create', 'teams.invite', 'mentorship.view_own',
            ],
            'company_contact' => [
                'organizations.create', 'organizations.edit_own', 'applications.view_own',
            ],
            'company_product_owner' => [
                'organizations.edit_own', 'applications.view_own', 'mentorship.view_own',
            ],
            'mentor' => [
                'mentorship.view_own', 'mentorship.manage', 'applications.view_all',
            ],
            'evaluator' => [
                'applications.view_all', 'applications.evaluate', 'applications.request_supplement',
            ],
            'editor' => [
                'content.view', 'content.create', 'content.edit', 'content.delete',
            ],
            'admin' => [
                'content.view', 'content.create', 'content.edit', 'content.delete',
                'users.view', 'users.edit',
                'calls.view', 'calls.create', 'calls.edit', 'calls.close',
                'applications.view_all', 'applications.evaluate',
                'applications.decide', 'applications.request_supplement',
                'organizations.view_all',
                'mentorship.manage',
                'reports.view', 'reports.export',
                'audit.view',
            ],
            'super_admin' => Permission::all()->pluck('name')->toArray(),
        ];

        foreach ($roleMap as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}

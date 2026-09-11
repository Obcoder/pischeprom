<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'sales_mailings.view',
            'sales_mailings.edit',
            'sales_mailings.send_test',
            'sales_mailings.send_mass',
            'sales_mailings.compliance_override',
            'sales_mailings.manage_templates',
            'sales_mailings.manage_suppression',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'crm');
        }

        Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'crm')
            ->first()
            ?->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve permissions and assignments that may have existed before this migration.
    }
};

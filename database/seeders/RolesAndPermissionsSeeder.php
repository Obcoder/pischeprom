<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]
            ->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',

            'orders.view',
            'orders.create',
            'orders.edit',
            'orders.delete',

            'warehouse.view',
            'warehouse.move',

            'products.view',
            'products.edit',

            'ai_price_lists.view',
            'ai_price_lists.process',
            'ai_price_lists.review',
            'ai_price_lists.assign_supplier',
            'ai_price_lists.apply',
            'ai_price_lists.view_technical',

            'users.view',
            'users.edit',

            'bank.view',
            'bank.view_sensitive',
            'bank.sync',
            'bank.reconcile',
            'bank.manage_connection',
            'bank.manage_payment_drafts',
            'bank.view_audit',

            'logistics.view',
            'logistics.trips.manage',
            'logistics.vehicles.manage',
            'logistics.expenses.manage',
            'logistics.matrix.manage',
            'logistics.technical.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'crm',
            ]);
        }

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'crm',
        ]);

        $admin->givePermissionTo($permissions);

        $manager = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'crm',
        ]);

        $manager->givePermissionTo([
            'dashboard.view',
            'orders.view',
            'orders.create',
            'orders.edit',
            'products.view',
            'ai_price_lists.view',
            'ai_price_lists.process',
            'ai_price_lists.review',
            'ai_price_lists.assign_supplier',
            'bank.view',
            'bank.view_sensitive',
            'bank.sync',
            'bank.reconcile',
            'bank.manage_payment_drafts',
            'logistics.view',
            'logistics.trips.manage',
            'logistics.vehicles.manage',
            'logistics.expenses.manage',
        ]);
    }
}

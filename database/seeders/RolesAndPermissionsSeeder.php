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

            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.procurement.view',
            'ai_sales.unit_roles.manage',
            'ai_sales.contexts.manage',
            'ai_sales.aliases.manage',
            'ai_sales.observation.manage',
            'ai_sales.observation.verify',
            'ai_sales.observation.promote',
            'ai_sales.entity.propose',
            'ai_sales.entity.create',
            'ai_sales.entity.link',
            'ai_sales.entity.merge',
            'ai_sales.classifications.view_internal',
            'ai_sales.audit.view',
            'ai_sales.control.view',
            'ai_sales.control.manage',
            'ai_sales.research.run',
            'ai_sales.runs.view',
            'ai_sales.runs.cancel',
            'ai_sales.capabilities.view',
            'ai_sales.residency.verify',
            'ai_sales.pricing.verify',
            'ai_sales.tools.view',
            'ai_sales.tools.execute',
            'ai_sales.workflows.execute',

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
            'ai_sales.view',
            'ai_sales.sales.view',
            'ai_sales.unit_roles.manage',
            'ai_sales.contexts.manage',
            'ai_sales.aliases.manage',
            'ai_sales.observation.manage',
            'ai_sales.observation.verify',
            'ai_sales.entity.propose',
            'ai_sales.control.view',
            'ai_sales.research.run',
            'ai_sales.runs.view',
            'ai_sales.runs.cancel',
            'ai_sales.tools.view',
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

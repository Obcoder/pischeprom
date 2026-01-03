<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

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

            'users.view',
            'users.edit',
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

        $admin->syncPermissions($permissions);

        $manager = Role::firstOrCreate([
                                           'name' => 'manager',
                                           'guard_name' => 'crm',
                                       ]);

        $manager->syncPermissions([
                                      'dashboard.view',
                                      'orders.view',
                                      'orders.create',
                                      'orders.edit',
                                      'products.view',
                                  ]);
    }
}

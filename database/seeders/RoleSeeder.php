<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
                'is_system_role' => true,
                'permissions' => ['*'], // All permissions
            ],
            [
                'name' => 'tenant_admin',
                'display_name' => 'Tenant Administrator',
                'description' => 'Full access to tenant workspace and team management',
                'is_system_role' => true,
                'permissions' => [
                    'dashboard.view', 'dashboard.analytics',
                    'users.view', 'users.create', 'users.update', 'users.delete', 'users.manage_roles', 'users.toggle_status',
                    'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.manage_permissions',
                    'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.import', 'customers.export',
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.print',
                    'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete', 'inventory.adjust',
                    'accounting.view', 'accounting.journal_entries', 'accounting.reports', 'accounting.chart_of_accounts',
                    'settings.view', 'settings.update', 'settings.backup',
                    'reports.view', 'reports.export',
                ],
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Management level access with most permissions',
                'is_system_role' => false,
                'permissions' => [
                    'dashboard.view', 'dashboard.analytics',
                    'users.view',
                    'customers.view', 'customers.create', 'customers.update', 'customers.export',
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.print',
                    'inventory.view', 'inventory.create', 'inventory.update', 'inventory.adjust',
                    'accounting.view', 'accounting.reports',
                    'reports.view', 'reports.export',
                ],
            ],
            [
                'name' => 'accountant',
                'display_name' => 'Accountant',
                'description' => 'Accounting and financial management access',
                'is_system_role' => false,
                'permissions' => [
                    'dashboard.view',
                    'customers.view', 'customers.create', 'customers.update',
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.print',
                    'accounting.view', 'accounting.journal_entries', 'accounting.reports', 'accounting.chart_of_accounts',
                    'reports.view', 'reports.export',
                ],
            ],
            [
                'name' => 'cashier',
                'display_name' => 'Cashier',
                'description' => 'Sales and customer service access',
                'is_system_role' => false,
                'permissions' => [
                    'dashboard.view',
                    'customers.view', 'customers.create', 'customers.update',
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.print',
                    'inventory.view',
                ],
            ],
            [
                'name' => 'inventory_manager',
                'display_name' => 'Inventory Manager',
                'description' => 'Inventory and product management access',
                'is_system_role' => false,
                'permissions' => [
                    'dashboard.view',
                    'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete', 'inventory.adjust',
                    'reports.view', 'reports.export',
                ],
            ],
            [
                'name' => 'employee',
                'display_name' => 'Employee',
                'description' => 'Basic employee access',
                'is_system_role' => false,
                'permissions' => [
                    'dashboard.view',
                    'customers.view',
                    'invoices.view',
                    'inventory.view',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = \App\Models\Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );

            // Assign permissions to role
            if ($permissions[0] === '*') {
                // Assign all permissions
                $allPermissions = \App\Models\Permission::all();
                $role->permissions()->sync($allPermissions->pluck('id'));
            } else {
                // Assign specific permissions
                $permissionIds = \App\Models\Permission::whereIn('name', $permissions)->pluck('id');
                $role->permissions()->sync($permissionIds);
            }
        }
    }
}

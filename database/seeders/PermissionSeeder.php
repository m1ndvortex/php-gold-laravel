<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'users.view', 'display_name' => 'View Users', 'description' => 'View user list and details', 'group' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'description' => 'Create new users', 'group' => 'users'],
            ['name' => 'users.update', 'display_name' => 'Update Users', 'description' => 'Update user information', 'group' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'description' => 'Delete users', 'group' => 'users'],
            ['name' => 'users.manage_roles', 'display_name' => 'Manage User Roles', 'description' => 'Assign roles to users', 'group' => 'users'],
            ['name' => 'users.toggle_status', 'display_name' => 'Toggle User Status', 'description' => 'Activate/deactivate users', 'group' => 'users'],

            // Role Management
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'description' => 'View role list and details', 'group' => 'roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'description' => 'Create new roles', 'group' => 'roles'],
            ['name' => 'roles.update', 'display_name' => 'Update Roles', 'description' => 'Update role information', 'group' => 'roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'description' => 'Delete roles', 'group' => 'roles'],
            ['name' => 'roles.manage_permissions', 'display_name' => 'Manage Role Permissions', 'description' => 'Assign permissions to roles', 'group' => 'roles'],

            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'description' => 'Access main dashboard', 'group' => 'dashboard'],
            ['name' => 'dashboard.analytics', 'display_name' => 'View Analytics', 'description' => 'View business analytics and reports', 'group' => 'dashboard'],

            // Customer Management
            ['name' => 'customers.view', 'display_name' => 'View Customers', 'description' => 'View customer list and details', 'group' => 'customers'],
            ['name' => 'customers.create', 'display_name' => 'Create Customers', 'description' => 'Create new customers', 'group' => 'customers'],
            ['name' => 'customers.update', 'display_name' => 'Update Customers', 'description' => 'Update customer information', 'group' => 'customers'],
            ['name' => 'customers.delete', 'display_name' => 'Delete Customers', 'description' => 'Delete customers', 'group' => 'customers'],
            ['name' => 'customers.import', 'display_name' => 'Import Customers', 'description' => 'Import customer data', 'group' => 'customers'],
            ['name' => 'customers.export', 'display_name' => 'Export Customers', 'description' => 'Export customer data', 'group' => 'customers'],

            // Invoice Management
            ['name' => 'invoices.view', 'display_name' => 'View Invoices', 'description' => 'View invoice list and details', 'group' => 'invoices'],
            ['name' => 'invoices.create', 'display_name' => 'Create Invoices', 'description' => 'Create new invoices', 'group' => 'invoices'],
            ['name' => 'invoices.update', 'display_name' => 'Update Invoices', 'description' => 'Update invoice information', 'group' => 'invoices'],
            ['name' => 'invoices.delete', 'display_name' => 'Delete Invoices', 'description' => 'Delete invoices', 'group' => 'invoices'],
            ['name' => 'invoices.print', 'display_name' => 'Print Invoices', 'description' => 'Print and generate PDF invoices', 'group' => 'invoices'],

            // Inventory Management
            ['name' => 'inventory.view', 'display_name' => 'View Inventory', 'description' => 'View product inventory', 'group' => 'inventory'],
            ['name' => 'inventory.create', 'display_name' => 'Create Products', 'description' => 'Create new products', 'group' => 'inventory'],
            ['name' => 'inventory.update', 'display_name' => 'Update Products', 'description' => 'Update product information', 'group' => 'inventory'],
            ['name' => 'inventory.delete', 'display_name' => 'Delete Products', 'description' => 'Delete products', 'group' => 'inventory'],
            ['name' => 'inventory.adjust', 'display_name' => 'Adjust Stock', 'description' => 'Make stock adjustments', 'group' => 'inventory'],

            // Accounting
            ['name' => 'accounting.view', 'display_name' => 'View Accounting', 'description' => 'View accounting information', 'group' => 'accounting'],
            ['name' => 'accounting.journal_entries', 'display_name' => 'Manage Journal Entries', 'description' => 'Create and manage journal entries', 'group' => 'accounting'],
            ['name' => 'accounting.reports', 'display_name' => 'View Financial Reports', 'description' => 'View financial reports', 'group' => 'accounting'],
            ['name' => 'accounting.chart_of_accounts', 'display_name' => 'Manage Chart of Accounts', 'description' => 'Manage chart of accounts', 'group' => 'accounting'],

            // Settings
            ['name' => 'settings.view', 'display_name' => 'View Settings', 'description' => 'View system settings', 'group' => 'settings'],
            ['name' => 'settings.update', 'display_name' => 'Update Settings', 'description' => 'Update system settings', 'group' => 'settings'],
            ['name' => 'settings.backup', 'display_name' => 'Backup Data', 'description' => 'Create and restore backups', 'group' => 'settings'],

            // Reports
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'description' => 'View business reports', 'group' => 'reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'description' => 'Export reports to PDF/Excel', 'group' => 'reports'],
        ];

        foreach ($permissions as $permission) {
            \App\Models\Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}

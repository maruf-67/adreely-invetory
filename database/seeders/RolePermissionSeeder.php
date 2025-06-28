<?php

namespace Database\Seeders;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create only the permissions needed for admin and staff
        $permissions = [
            'manage-users',
            'manage-orders',
            'view-reports',
            'manage-products',
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $staff = Role::firstOrCreate(['name' => 'staff']);

        // Assign permissions
        $superAdmin->syncPermissions(Permission::all());
        $admin->syncPermissions(['manage-users', 'manage-orders', 'view-reports', 'manage-products']);
        $staff->syncPermissions(['manage-orders']);

        // Create super-admin user
        $superAdminUser = User::firstOrCreate([
            'email' => 'super@admin.com',
        ], [
            'name' => 'Super Admin',
            'password' => bcrypt('12345678'),
            'user_type' => 'admin',
        ]);
        $superAdminUser->assignRole('super-admin');

        // Optionally, create an admin and staff user for testing
        $adminUser = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin User',
            'password' => bcrypt('adminpassword'),
            'user_type' => 'admin',
        ]);
        $adminUser->assignRole('admin');

        $staffUser = User::firstOrCreate([
            'email' => 'staff@example.com',
        ], [
            'name' => 'Staff User',
            'password' => bcrypt('staffpassword'),
            'user_type' => 'staff',
        ]);
        $staffUser->assignRole('staff');
    }
}

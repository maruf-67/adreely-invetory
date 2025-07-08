<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Business;
use App\Enums\Permission;

class BusinessPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all businesses and set default staff permissions
        $businesses = Business::all();
        
        $defaultPermissions = [
            Permission::VIEW_PRODUCTS->value,
            Permission::CREATE_PRODUCTS->value,
            Permission::EDIT_PRODUCTS->value,
            Permission::VIEW_CATEGORIES->value,
            Permission::CREATE_CATEGORIES->value,
            Permission::VIEW_BRANDS->value,
            Permission::CREATE_BRANDS->value,
            Permission::VIEW_UNITS->value,
            Permission::CREATE_UNITS->value,
            Permission::VIEW_PURCHASE_ORDERS->value,
            Permission::CREATE_PURCHASE_ORDERS->value,
            Permission::VIEW_SALES_ORDERS->value,
            Permission::CREATE_SALES_ORDERS->value,
            Permission::VIEW_EXPENSES->value,
            Permission::CREATE_EXPENSES->value,
            Permission::VIEW_USERS->value,
        ];

        foreach ($businesses as $business) {
            if (empty($business->staff_permissions)) {
                $business->staff_permissions = $defaultPermissions;
                $business->save();
            }
        }
    }
}

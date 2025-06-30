<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Business;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use App\Models\Product;
use App\Models\PaymentMethod;
use App\Models\ExpenseCategory;
use App\Models\ExtraIncomeType;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class ComprehensiveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'manage-users',
            'manage-orders', 
            'view-reports',
            'manage-products',
            'manage-payments',
            'manage-expenses',
            'manage-inventory'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $staffRole = Role::firstOrCreate(['name' => 'staff']);

        $adminRole->syncPermissions($permissions);
        $staffRole->syncPermissions(['manage-orders', 'manage-products', 'manage-inventory']);

        // Create sample businesses
        $business1 = Business::firstOrCreate([
            'name' => 'Tech Solutions Ltd'
        ], [
            'address' => '123 Business Park, Tech City',
            'phone' => '+1234567890',
            'email' => 'contact@techsolutions.com',
            'description' => 'A technology solutions company',
            'is_active' => true,
        ]);

        $business2 = Business::firstOrCreate([
            'name' => 'Green Foods Co'
        ], [
            'address' => '456 Organic Street, Green Valley',
            'phone' => '+1234567891',
            'email' => 'info@greenfoods.com',
            'description' => 'Organic food distribution company',
            'is_active' => true,
        ]);

        // Create admin users for each business
        $admin1 = User::firstOrCreate([
            'email' => 'admin@techsolutions.com'
        ], [
            'business_id' => $business1->id,
            'name' => 'John Admin',
            'phone' => '+1234567890',
            'password' => Hash::make('password123'),
            'user_type' => 'admin',
            'current_balance' => 0,
        ]);

        $admin2 = User::firstOrCreate([
            'email' => 'admin@greenfoods.com'
        ], [
            'business_id' => $business2->id,
            'name' => 'Jane Admin',
            'phone' => '+1234567891',
            'password' => Hash::make('password123'),
            'user_type' => 'admin',
            'current_balance' => 0,
        ]);

        // Update business owner
        $business1->update(['owner_id' => $admin1->id]);
        $business2->update(['owner_id' => $admin2->id]);

        $admin1->assignRole('admin');
        $admin2->assignRole('admin');

        foreach ([$business1, $business2] as $business) {
            $admin = $business->owner;

            // Create staff users
            $staff = User::create([
                'business_id' => $business->id,
                'name' => 'Staff User',
                'phone' => '+123456788' . $business->id,
                'email' => 'staff' . $business->id . '@example.com',
                'password' => Hash::make('password123'),
                'user_type' => 'staff',
                'current_balance' => 0,
                'created_by' => $admin->id,
            ]);
            $staff->assignRole('staff');

            // Create suppliers
            User::create([
                'business_id' => $business->id,
                'name' => 'ABC Suppliers',
                'phone' => '+123456787' . $business->id,
                'email' => 'supplier1@business' . $business->id . '.com',
                'user_type' => 'supplier',
                'party_type' => 'Regular',
                'current_balance' => 0,
                'created_by' => $admin->id,
            ]);

            // Create customers
            User::create([
                'business_id' => $business->id,
                'name' => 'Retail Store ' . $business->id,
                'phone' => '+123456786' . $business->id,
                'email' => 'retailer1@business' . $business->id . '.com',
                'user_type' => 'retailer',
                'party_type' => 'Priority',
                'current_balance' => 0,
                'created_by' => $admin->id,
            ]);

            User::create([
                'business_id' => $business->id,
                'name' => 'Dealer Network ' . $business->id,
                'phone' => '+123456785' . $business->id,
                'user_type' => 'dealer',
                'party_type' => 'Regular',
                'current_balance' => 0,
                'created_by' => $admin->id,
            ]);

            // Create categories
            $categories = [
                'Electronics',
                'Clothing',
                'Food & Beverages',
                'Office Supplies',
                'Medical Equipment'
            ];

            foreach ($categories as $categoryName) {
                Category::create([
                    'business_id' => $business->id,
                    'name' => $categoryName
                ]);
            }

            // Create brands
            $brands = [
                'Samsung',
                'Apple',
                'Nike',
                'Adidas',
                'Dell'
            ];

            foreach ($brands as $brandName) {
                Brand::create([
                    'business_id' => $business->id,
                    'name' => $brandName
                ]);
            }

            // Create units
            $units = [
                ['name' => 'Pieces', 'short_name' => 'pcs'],
                ['name' => 'Kilograms', 'short_name' => 'kg'],
                ['name' => 'Liters', 'short_name' => 'l'],
                ['name' => 'Meters', 'short_name' => 'm'],
                ['name' => 'Boxes', 'short_name' => 'box']
            ];

            foreach ($units as $unit) {
                Unit::create([
                    'business_id' => $business->id,
                    'name' => $unit['name'],
                    'short_name' => $unit['short_name']
                ]);
            }

            // Create sample products
            $businessCategories = Category::where('business_id', $business->id)->get();
            $businessBrands = Brand::where('business_id', $business->id)->get();
            $businessUnits = Unit::where('business_id', $business->id)->get();

            $products = [
                [
                    'name' => 'Laptop Computer',
                    'sku' => 'LAP001',
                    'purchase_price' => 800.00,
                    'selling_price' => 1200.00,
                    'quantity' => 50,
                    'low_stock_threshold' => 10
                ],
                [
                    'name' => 'Wireless Mouse',
                    'sku' => 'MOU001',
                    'purchase_price' => 15.00,
                    'selling_price' => 25.00,
                    'quantity' => 100,
                    'low_stock_threshold' => 20
                ],
                [
                    'name' => 'Office Chair',
                    'sku' => 'CHR001',
                    'purchase_price' => 120.00,
                    'selling_price' => 200.00,
                    'quantity' => 25,
                    'low_stock_threshold' => 5
                ],
                [
                    'name' => 'Printer Paper',
                    'sku' => 'PAP001',
                    'purchase_price' => 5.00,
                    'selling_price' => 8.00,
                    'quantity' => 200,
                    'low_stock_threshold' => 50
                ],
                [
                    'name' => 'USB Cable',
                    'sku' => 'USB001',
                    'purchase_price' => 3.00,
                    'selling_price' => 8.00,
                    'quantity' => 8, // Low stock for testing alerts
                    'low_stock_threshold' => 10
                ]
            ];

            foreach ($products as $productData) {
                Product::create([
                    'business_id' => $business->id,
                    'name' => $productData['name'],
                    'sku' => $productData['sku'],
                    'category_id' => $businessCategories->random()->id,
                    'brand_id' => $businessBrands->random()->id,
                    'unit_id' => $businessUnits->random()->id,
                    'description' => 'Sample product description for ' . $productData['name'],
                    'purchase_price' => $productData['purchase_price'],
                    'selling_price' => $productData['selling_price'],
                    'quantity' => $productData['quantity'],
                    'low_stock_threshold' => $productData['low_stock_threshold']
                ]);
            }

            // Create payment methods
            $paymentMethods = [
                ['gateway_name' => 'Cash', 'account_name' => 'Cash Register'],
                ['gateway_name' => 'Bank Transfer', 'account_name' => 'Business Account', 'account_number' => '1234567890', 'branch' => 'Main Branch'],
                ['gateway_name' => 'Cheque', 'account_name' => 'Business Account'],
                ['gateway_name' => 'Credit Card', 'account_name' => 'Merchant Account'],
                ['gateway_name' => 'Mobile Money', 'account_name' => 'Mobile Wallet']
            ];

            foreach ($paymentMethods as $method) {
                PaymentMethod::create([
                    'business_id' => $business->id,
                    'gateway_name' => $method['gateway_name'],
                    'account_name' => $method['account_name'],
                    'account_number' => $method['account_number'] ?? null,
                    'branch' => $method['branch'] ?? null,
                    'currency' => 'USD',
                    'is_active' => true
                ]);
            }

            // Create expense categories
            $expenseCategories = [
                'Office Rent',
                'Utilities',
                'Marketing',
                'Transportation',
                'Office Supplies',
                'Insurance',
                'Maintenance',
                'Professional Services'
            ];

            foreach ($expenseCategories as $categoryName) {
                ExpenseCategory::create([
                    'business_id' => $business->id,
                    'name' => $categoryName
                ]);
            }

            // Create extra income types
            $incomeTypes = [
                'Service Revenue',
                'Consulting',
                'Interest Income',
                'Rental Income',
                'Commission',
                'Other Income'
            ];

            foreach ($incomeTypes as $typeName) {
                ExtraIncomeType::create([
                    'business_id' => $business->id,
                    'name' => $typeName
                ]);
            }
        }

        $this->command->info('Sample data seeded successfully!');
        $this->command->info('Admin credentials:');
        $this->command->info('Business 1: admin@techsolutions.com / password123');
        $this->command->info('Business 2: admin@greenfoods.com / password123');
        $this->command->info('Staff credentials:');
        $this->command->info('Business 1: staff1@example.com / password123');
        $this->command->info('Business 2: staff2@example.com / password123');
    }
}

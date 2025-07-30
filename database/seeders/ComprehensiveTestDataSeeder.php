<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use App\Models\Product;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\UserBalance;
use App\Models\InventoryHistory;
use App\Models\EmployeeSalary;
use App\Models\Investor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ComprehensiveTestDataSeeder extends Seeder
{
    private $businesses = [];
    private $users = [];
    private $categories = [];
    private $brands = [];
    private $units = [];
    private $products = [];
    private $paymentMethods = [];
    private $expenseCategories = [];

    public function run(): void
    {
        $this->command->info('🚀 Starting Comprehensive Test Data Seeding...');
        
        // Create businesses first
        $this->createBusinesses();
        
        // Create users for each business
        $this->createUsers();
        
        // Create basic master data
        $this->createCategories();
        $this->createBrands();
        $this->createUnits();
        $this->createPaymentMethods();
        $this->createExpenseCategories();
        
        // Create products
        $this->createProducts();
        
        // Create purchase orders and shipments
        $this->createPurchaseOrders();
        
        // Create sales orders and shipments
        $this->createSalesOrders();
        
        // Create expenses
        $this->createExpenses();
        
        // Create employee salaries
        $this->createEmployeeSalaries();
        
        // Create investors
        $this->createInvestors();
        
        // Create user balance adjustments
        $this->createUserBalanceAdjustments();
        
        $this->command->info('✅ Comprehensive Test Data Seeding Completed!');
        $this->printSummary();
    }

    private function createBusinesses(): void
    {
        $this->command->info('📊 Creating businesses...');
        
                $businessData = [
            [
                'name' => 'TechVibe Electronics',
                'email' => 'contact@techvibe.com',
                'phone' => '+1-555-0101',
                'address' => '123 Innovation Drive, Palo Alto, CA 94000',
                'description' => 'Electronics retail and wholesale business',
            ],
            [
                'name' => 'FreshMart Grocery',
                'email' => 'info@freshmart.com',
                'phone' => '+1-555-0202',
                'address' => '456 Market Square, Downtown, NY 10001',
                'description' => 'Grocery and retail store chain',
            ],
            [
                'name' => 'AutoParts Central',
                'email' => 'contact@autoparts.com',
                'phone' => '+1-555-0303',
                'address' => '789 Industrial Blvd, Detroit, MI 48201',
                'description' => 'Automotive parts wholesale and retail',
            ]
        ];

        foreach ($businessData as $index => $data) {
            $business = Business::create($data);
            // Add a temporary type property for seeder logic
            if (str_contains($business->name, 'Electronics')) {
                $business->temp_type = 'electronics';
            } elseif (str_contains($business->name, 'Grocery')) {
                $business->temp_type = 'retail';
            } else {
                $business->temp_type = 'automotive';
            }
            $this->businesses[] = $business;
        }
    }

    private function createUsers(): void
    {
        $this->command->info('👥 Creating users...');
        
        foreach ($this->businesses as $business) {
            // Create admin user for each business
            $admin = User::create([
                'business_id' => $business->id,
                'name' => 'Admin ' . $business->name,
                'email' => 'admin@' . strtolower(str_replace(' ', '', $business->name)) . '.com',
                'phone' => '+1-555-' . str_pad($business->id . '000', 4, '0'),
                'user_type' => 'admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]);
            $this->users[$business->id]['admin'] = $admin;

            // Create staff users (employees)
            $staffData = [
                [
                    'name' => 'John Manager',
                    'email' => 'john.manager@' . strtolower(str_replace(' ', '', $business->name)) . '.com',
                    'phone' => '+1-555-' . str_pad($business->id . '001', 4, '0'),
                    'user_type' => 'staff',
                    'join_date' => Carbon::now()->subMonths(12),
                    'salary_amount' => 75000.00,
                    'address' => '100 Employee St, City',
                ],
                [
                    'name' => 'Sarah Clerk',
                    'email' => 'sarah.clerk@' . strtolower(str_replace(' ', '', $business->name)) . '.com',
                    'phone' => '+1-555-' . str_pad($business->id . '002', 4, '0'),
                    'user_type' => 'staff',
                    'join_date' => Carbon::now()->subMonths(8),
                    'salary_amount' => 45000.00,
                    'address' => '200 Employee St, City',
                ],
                [
                    'name' => 'Mike Warehouse',
                    'email' => 'mike.warehouse@' . strtolower(str_replace(' ', '', $business->name)) . '.com',
                    'phone' => '+1-555-' . str_pad($business->id . '003', 4, '0'),
                    'user_type' => 'staff',
                    'join_date' => Carbon::now()->subMonths(6),
                    'salary_amount' => 40000.00,
                    'address' => '300 Employee St, City',
                ]
            ];

            foreach ($staffData as $data) {
                $data['business_id'] = $business->id;
                $data['password'] = Hash::make('password123');
                $data['created_by'] = $admin->id;
                $staff = User::create($data);
                $this->users[$business->id]['staff'][] = $staff;
            }

            // Create suppliers
            $supplierData = [
                [
                    'name' => 'Global Electronics Supply Co.',
                    'email' => 'sales' . $business->id . '@globalsupply.com',
                    'phone' => '+1-800-' . str_pad($business->id . '101', 4, '0'),
                    'user_type' => 'supplier',
                    'party_type' => 'Priority',
                    'address' => '500 Supply Chain Ave, Industrial Zone',
                    'previous_credit' => 5000.00,
                ],
                [
                    'name' => 'Premium Parts Ltd.',
                    'email' => 'orders' . $business->id . '@premiumparts.com',
                    'phone' => '+1-800-' . str_pad($business->id . '102', 4, '0'),
                    'user_type' => 'supplier',
                    'party_type' => 'Priority',
                    'address' => '600 Wholesale District, Commerce City',
                    'previous_due' => 2500.00,
                ],
                [
                    'name' => 'Local Vendor Inc.',
                    'email' => 'info' . $business->id . '@localvendor.com',
                    'phone' => '+1-800-' . str_pad($business->id . '103', 4, '0'),
                    'user_type' => 'supplier',
                    'party_type' => 'Regular',
                    'address' => '700 Local Market St, Hometown',
                ]
            ];

            foreach ($supplierData as $data) {
                $data['business_id'] = $business->id;
                $data['created_by'] = $admin->id;
                $supplier = User::create($data);
                $this->users[$business->id]['suppliers'][] = $supplier;
            }

            // Create customers (using retailer user_type as customer is not in enum)
            $customerData = [
                [
                    'name' => 'ABC Retail Chain',
                    'email' => 'purchasing' . $business->id . '@abcretail.com',
                    'phone' => '+1-900-' . str_pad($business->id . '201', 4, '0'),
                    'user_type' => 'retailer',
                    'party_type' => 'Priority',
                    'address' => '800 Retail Plaza, Shopping District',
                    'previous_due' => 15000.00,
                ],
                [
                    'name' => 'XYZ Corporation',
                    'email' => 'orders' . $business->id . '@xyzcorp.com',
                    'phone' => '+1-900-' . str_pad($business->id . '202', 4, '0'),
                    'user_type' => 'wholesaler',
                    'party_type' => 'Priority',
                    'address' => '900 Corporate Blvd, Business Park',
                    'previous_credit' => 3000.00,
                ],
                [
                    'name' => 'John Smith',
                    'email' => 'john.smith' . $business->id . '@email.com',
                    'phone' => '+1-900-' . str_pad($business->id . '203', 4, '0'),
                    'user_type' => 'guest',
                    'party_type' => 'Regular',
                    'address' => '1000 Residential Ave, Suburb',
                ],
                [
                    'name' => 'Jane Doe Enterprises',
                    'email' => 'jane' . $business->id . '@janedoe.com',
                    'phone' => '+1-900-' . str_pad($business->id . '204', 4, '0'),
                    'user_type' => 'dealer',
                    'party_type' => 'Regular',
                    'address' => '1100 Main Street, Town Center',
                    'current_balance' => 1500.00,
                ]
            ];

            foreach ($customerData as $data) {
                $data['business_id'] = $business->id;
                $data['created_by'] = $admin->id;
                $customer = User::create($data);
                $this->users[$business->id]['buyers'][] = $customer; // Changed from 'customers' to 'buyers'
            }

            // Create other user types
            $otherUserData = [
                [
                    'name' => 'Wholesale Partner Ltd.',
                    'email' => 'contact' . $business->id . '@wholesalepartner.com',
                    'phone' => '+1-700-' . str_pad($business->id . '301', 4, '0'),
                    'user_type' => 'wholesaler',
                    'party_type' => 'Priority',
                    'address' => '1200 Wholesale District',
                ],
                [
                    'name' => 'Dealer Network Inc.',
                    'email' => 'sales' . $business->id . '@dealernetwork.com',
                    'phone' => '+1-700-' . str_pad($business->id . '302', 4, '0'),
                    'user_type' => 'dealer',
                    'party_type' => 'Priority',
                    'address' => '1300 Dealer Row',
                ],
                [
                    'name' => 'Retail Partner Co.',
                    'email' => 'info' . $business->id . '@retailpartner.com',
                    'phone' => '+1-700-' . str_pad($business->id . '303', 4, '0'),
                    'user_type' => 'retailer',
                    'party_type' => 'Regular',
                    'address' => '1400 Retail Avenue',
                ]
            ];

            foreach ($otherUserData as $data) {
                $data['business_id'] = $business->id;
                $data['created_by'] = $admin->id;
                $user = User::create($data);
                $this->users[$business->id]['others'][] = $user;
            }
        }
    }

    private function createCategories(): void
    {
        $this->command->info('📁 Creating categories...');
        
        foreach ($this->businesses as $business) {
            $categoryData = [];
            
            if ($business->temp_type === 'electronics') {
                $categoryData = [
                    ['name' => 'Smartphones', 'description' => 'Mobile phones and accessories'],
                    ['name' => 'Laptops', 'description' => 'Portable computers'],
                    ['name' => 'Tablets', 'description' => 'Tablet devices'],
                    ['name' => 'Audio Equipment', 'description' => 'Headphones, speakers, etc.'],
                    ['name' => 'Gaming', 'description' => 'Gaming consoles and accessories'],
                ];
            } elseif ($business->temp_type === 'retail') {
                $categoryData = [
                    ['name' => 'Fresh Produce', 'description' => 'Fruits and vegetables'],
                    ['name' => 'Dairy Products', 'description' => 'Milk, cheese, yogurt'],
                    ['name' => 'Beverages', 'description' => 'Drinks and juices'],
                    ['name' => 'Snacks', 'description' => 'Chips, cookies, candy'],
                    ['name' => 'Household Items', 'description' => 'Cleaning supplies, toiletries'],
                ];
            } else {
                $categoryData = [
                    ['name' => 'Engine Parts', 'description' => 'Engine components'],
                    ['name' => 'Brake System', 'description' => 'Brake pads, rotors, etc.'],
                    ['name' => 'Electrical', 'description' => 'Batteries, alternators'],
                    ['name' => 'Tires', 'description' => 'All season and specialty tires'],
                    ['name' => 'Filters', 'description' => 'Oil, air, fuel filters'],
                ];
            }

            foreach ($categoryData as $data) {
                $data['business_id'] = $business->id;
                $data['created_by'] = $this->users[$business->id]['admin']->id;
                $category = Category::create($data);
                $this->categories[$business->id][] = $category;
            }
        }
    }

    private function createBrands(): void
    {
        $this->command->info('🏷️ Creating brands...');
        
        foreach ($this->businesses as $business) {
            $brandData = [];
            
            if ($business->temp_type === 'electronics') {
                $brandData = [
                    ['name' => 'Apple'],
                    ['name' => 'Samsung'],
                    ['name' => 'Sony'],
                    ['name' => 'Dell'],
                    ['name' => 'HP'],
                ];
            } elseif ($business->temp_type === 'retail') {
                $brandData = [
                    ['name' => 'FreshMart Brand'],
                    ['name' => 'Organic Valley'],
                    ['name' => 'Coca-Cola'],
                    ['name' => 'Nestle'],
                    ['name' => 'Procter & Gamble'],
                ];
            } else {
                $brandData = [
                    ['name' => 'Bosch'],
                    ['name' => 'ACDelco'],
                    ['name' => 'Mobil 1'],
                    ['name' => 'Michelin'],
                    ['name' => 'Champion'],
                ];
            }

            foreach ($brandData as $data) {
                $data['business_id'] = $business->id;
                $data['created_by'] = $this->users[$business->id]['admin']->id;
                $brand = Brand::create($data);
                $this->brands[$business->id][] = $brand;
            }
        }
    }

    private function createUnits(): void
    {
        $this->command->info('📏 Creating units...');
        
        foreach ($this->businesses as $business) {
            $unitData = [
                ['name' => 'Piece', 'short_name' => 'pcs'],
                ['name' => 'Kilogram', 'short_name' => 'kg'],
                ['name' => 'Liter', 'short_name' => 'L'],
                ['name' => 'Box', 'short_name' => 'box'],
                ['name' => 'Set', 'short_name' => 'set'],
            ];

            foreach ($unitData as $data) {
                $data['business_id'] = $business->id;
                $data['created_by'] = $this->users[$business->id]['admin']->id;
                $unit = Unit::create($data);
                $this->units[$business->id][] = $unit;
            }
        }
    }

    private function createPaymentMethods(): void
    {
        $this->command->info('💳 Creating payment methods...');
        
        foreach ($this->businesses as $business) {
            $methodData = [
                ['name' => 'Cash', 'type' => 'cash', 'is_active' => true],
                ['name' => 'Bank Transfer', 'type' => 'bank_transfer', 'is_active' => true],
                ['name' => 'Credit Card', 'type' => 'credit_card', 'is_active' => true],
                ['name' => 'Check', 'type' => 'check', 'is_active' => true],
                ['name' => 'Digital Wallet', 'type' => 'digital_wallet', 'is_active' => true],
            ];

            foreach ($methodData as $data) {
                $data['business_id'] = $business->id;
                $data['created_by'] = $this->users[$business->id]['admin']->id;
                $method = PaymentMethod::create($data);
                $this->paymentMethods[$business->id][] = $method;
            }
        }
    }

    private function createExpenseCategories(): void
    {
        $this->command->info('💰 Creating expense categories...');
        
        foreach ($this->businesses as $business) {
            $categoryData = [
                ['name' => 'Office Supplies', 'description' => 'Stationery, paper, etc.'],
                ['name' => 'Utilities', 'description' => 'Electricity, water, internet'],
                ['name' => 'Marketing', 'description' => 'Advertising and promotion'],
                ['name' => 'Transportation', 'description' => 'Vehicle expenses, fuel'],
                ['name' => 'Equipment', 'description' => 'Tools and machinery'],
                ['name' => 'Professional Services', 'description' => 'Legal, accounting, consulting'],
            ];

            foreach ($categoryData as $data) {
                $data['business_id'] = $business->id;
                $data['created_by'] = $this->users[$business->id]['admin']->id;
                $category = ExpenseCategory::create($data);
                $this->expenseCategories[$business->id][] = $category;
            }
        }
    }

    private function createProducts(): void
    {
        $this->command->info('📦 Creating products...');
        
        foreach ($this->businesses as $business) {
            $productData = [];
            
            if ($business->temp_type === 'electronics') {
                $productData = [
                    [
                        'name' => 'iPhone 15 Pro',
                        'sku' => 'IPH15P-128-BLK',
                        'description' => 'Latest iPhone with 128GB storage in black',
                        'purchase_price' => 800.00,
                        'selling_price' => 999.00,
                        'low_stock_threshold' => 10,
                        'category_idx' => 0, // Smartphones
                        'brand_idx' => 0, // Apple
                        'unit_idx' => 0, // Piece
                    ],
                    [
                        'name' => 'Samsung Galaxy S24',
                        'sku' => 'SGS24-256-WHT',
                        'description' => 'Samsung flagship with 256GB storage',
                        'purchase_price' => 700.00,
                        'selling_price' => 899.00,
                        'low_stock_threshold' => 8,
                        'category_idx' => 0, // Smartphones
                        'brand_idx' => 1, // Samsung
                        'unit_idx' => 0, // Piece
                    ],
                    [
                        'name' => 'MacBook Pro M3',
                        'sku' => 'MBP-M3-512-SLV',
                        'description' => '14-inch MacBook Pro with M3 chip',
                        'purchase_price' => 1800.00,
                        'selling_price' => 2199.00,
                        'low_stock_threshold' => 5,
                        'category_idx' => 1, // Laptops
                        'brand_idx' => 0, // Apple
                        'unit_idx' => 0, // Piece
                    ]
                ];
            } elseif ($business->temp_type === 'retail') {
                $productData = [
                    [
                        'name' => 'Organic Apples',
                        'sku' => 'ORG-APL-RED-1KG',
                        'description' => 'Fresh organic red apples',
                        'purchase_price' => 3.50,
                        'selling_price' => 5.99,
                        'low_stock_threshold' => 50,
                        'category_idx' => 0, // Fresh Produce
                        'brand_idx' => 1, // Organic Valley
                        'unit_idx' => 1, // Kilogram
                    ],
                    [
                        'name' => 'Whole Milk',
                        'sku' => 'MILK-WHL-1L',
                        'description' => 'Fresh whole milk 1 liter',
                        'purchase_price' => 1.20,
                        'selling_price' => 2.49,
                        'low_stock_threshold' => 100,
                        'category_idx' => 1, // Dairy Products
                        'brand_idx' => 0, // FreshMart Brand
                        'unit_idx' => 2, // Liter
                    ]
                ];
            } else {
                $productData = [
                    [
                        'name' => 'Brake Pads Set',
                        'sku' => 'BRK-PAD-F150',
                        'description' => 'Front brake pads for Ford F-150',
                        'purchase_price' => 45.00,
                        'selling_price' => 75.00,
                        'low_stock_threshold' => 20,
                        'category_idx' => 1, // Brake System
                        'brand_idx' => 0, // Bosch
                        'unit_idx' => 4, // Set
                    ],
                    [
                        'name' => 'Motor Oil 5W-30',
                        'sku' => 'OIL-5W30-5L',
                        'description' => 'Synthetic motor oil 5 liter',
                        'purchase_price' => 25.00,
                        'selling_price' => 39.99,
                        'low_stock_threshold' => 30,
                        'category_idx' => 0, // Engine Parts
                        'brand_idx' => 2, // Mobil 1
                        'unit_idx' => 2, // Liter
                    ]
                ];
            }

            foreach ($productData as $data) {
                $categoryIdx = $data['category_idx'];
                $brandIdx = $data['brand_idx'];
                $unitIdx = $data['unit_idx'];
                
                unset($data['category_idx'], $data['brand_idx'], $data['unit_idx']);
                
                $data['business_id'] = $business->id;
                $data['category_id'] = $this->categories[$business->id][$categoryIdx]->id;
                $data['brand_id'] = $this->brands[$business->id][$brandIdx]->id;
                $data['unit_id'] = $this->units[$business->id][$unitIdx]->id;
                $data['buying_unit_id'] = $this->units[$business->id][$unitIdx]->id;
                $data['quantity'] = 0; // Start with 0, will be updated by purchase orders
                $data['created_by'] = $this->users[$business->id]['admin']->id;
                
                $product = Product::create($data);
                $this->products[$business->id][] = $product;
            }
        }
    }

    private function createPurchaseOrders(): void
    {
        $this->command->info('🛒 Creating purchase orders...');
        
        foreach ($this->businesses as $business) {
            $suppliers = $this->users[$business->id]['suppliers'];
            $products = $this->products[$business->id];
            $paymentMethods = $this->paymentMethods[$business->id];
            $admin = $this->users[$business->id]['admin'];

            // Create 3-5 purchase orders per business
            for ($i = 0; $i < rand(3, 5); $i++) {
                $supplier = $suppliers[array_rand($suppliers)];
                $orderDate = Carbon::now()->subDays(rand(1, 60));
                
                // Create purchase order
                $purchaseOrder = PurchaseOrder::create([
                    'business_id' => $business->id,
                    'supplier_id' => $supplier->id,
                    'order_date' => $orderDate,
                    'expected_delivery_date' => $orderDate->copy()->addDays(rand(7, 21)),
                    'status' => 'completed',
                    'sub_total' => 0,
                    'discount' => 0,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'notes' => 'Test purchase order #' . ($i + 1),
                    'created_by' => $admin->id,
                ]);

                // Generate order number manually to avoid race conditions
                $date = $orderDate->format('Ymd');
                $businessPrefix = str_pad($business->id, 2, '0', STR_PAD_LEFT);
                $orderSequence = str_pad($i + 1, 3, '0', STR_PAD_LEFT);
                $orderNumber = "PO-{$date}-{$businessPrefix}{$orderSequence}";
                $purchaseOrder->update(['order_number' => $orderNumber]);

                // Add 2-4 items to each order
                $subTotal = 0;
                $selectedProducts = collect($products)->random(rand(2, min(4, count($products))));
                
                foreach ($selectedProducts as $product) {
                    $quantity = rand(10, 50);
                    $unitPrice = $product->purchase_price;
                    $totalPrice = $quantity * $unitPrice;
                    $subTotal += $totalPrice;

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_id' => $product->id,
                        'quantity_ordered' => $quantity,
                        'quantity_received' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'created_by' => $admin->id,
                    ]);

                    // Update product stock
                    $quantityBefore = $product->quantity;
                    $product->increment('quantity', $quantity);

                    // Create inventory history
                    InventoryHistory::create([
                        'business_id' => $business->id,
                        'product_id' => $product->id,
                        'user_id' => $admin->id,
                        'type' => 'stock-in',
                        'quantity_change' => $quantity,
                        'quantity_before' => $quantityBefore,
                        'quantity_after' => $quantityBefore + $quantity,
                        'reason' => "Purchase Order Received: {$purchaseOrder->order_number}",
                        'reference_type' => PurchaseOrder::class,
                        'reference_id' => $purchaseOrder->id,
                    ]);
                }

                // Update order totals
                $totalAmount = $subTotal;
                $purchaseOrder->update([
                    'sub_total' => $subTotal,
                    'total_amount' => $totalAmount,
                ]);

                // Create payments (some fully paid, some partial)
                $paymentRatio = rand(70, 100) / 100; // 70-100% paid
                $paidAmount = $totalAmount * $paymentRatio;
                
                if ($paidAmount > 0) {
                    $payment = Payment::create([
                        'business_id' => $business->id,
                        'paymentable_type' => PurchaseOrder::class,
                        'paymentable_id' => $purchaseOrder->id,
                        'payment_method_id' => $paymentMethods[array_rand($paymentMethods)]->id,
                        'amount' => $paidAmount,
                        'transaction_date' => $orderDate->copy()->addDays(rand(1, 7)),
                        'status' => 'clear',
                        'reference_number' => 'PAY-' . strtoupper(uniqid()),
                        'details' => 'Payment for purchase order',
                        'created_by' => $admin->id,
                    ]);

                    $purchaseOrder->update(['paid_amount' => $paidAmount]);

                    // Update supplier balance if there's remaining due
                    $remainingDue = $totalAmount - $paidAmount;
                    if ($remainingDue > 0) {
                        $supplier->refresh(); // Refresh to get current balance
                        $previousBalance = $supplier->current_balance;
                        $supplier->increment('current_balance', $remainingDue);
                        
                        UserBalance::create([
                            'business_id' => $business->id,
                            'user_id' => $supplier->id,
                            'balance_type' => 'debit',
                            'amount' => $remainingDue,
                            'previous_balance' => $previousBalance,
                            'new_balance' => $previousBalance + $remainingDue,
                            'transaction_date' => $orderDate,
                            'description' => "Outstanding amount for Purchase Order {$purchaseOrder->order_number}",
                            'balanceable_type' => PurchaseOrder::class,
                            'balanceable_id' => $purchaseOrder->id,
                            'created_by' => $admin->id,
                        ]);
                    }
                }
            }
        }
    }

    private function createSalesOrders(): void
    {
        $this->command->info('🛍️ Creating sales orders...');
        
        foreach ($this->businesses as $business) {
            $customers = $this->users[$business->id]['buyers'];
            $products = $this->products[$business->id];
            $paymentMethods = $this->paymentMethods[$business->id];
            $admin = $this->users[$business->id]['admin'];

            // Create 4-7 sales orders per business
            for ($i = 0; $i < rand(4, 7); $i++) {
                $customer = $customers[array_rand($customers)];
                $orderDate = Carbon::now()->subDays(rand(1, 30));
                
                // Determine order status with more variation
                $statusWeights = [
                    'pending' => 30,    // 30% chance
                    'partial' => 25,    // 25% chance  
                    'completed' => 25,  // 25% chance
                    'shipped' => 20     // 20% chance
                ];
                $statuses = [];
                foreach ($statusWeights as $status => $weight) {
                    $statuses = array_merge($statuses, array_fill(0, $weight, $status));
                }
                $status = $statuses[array_rand($statuses)];
                
                // Create sales order
                $salesOrder = SalesOrder::create([
                    'business_id' => $business->id,
                    'customer_id' => $customer->id,
                    'order_date' => $orderDate,
                    'expected_delivery_date' => $orderDate->copy()->addDays(rand(3, 14)),
                    'status' => $status,
                    'sub_total' => 0,
                    'discount' => rand(0, 1) ? rand(50, 500) : 0,
                    'discount_type' => 'fixed',
                    'tax_rate' => 8.5,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'notes' => 'Test sales order #' . ($i + 1),
                    'created_by' => $admin->id,
                ]);

                // Generate order number manually to avoid race conditions
                $date = $orderDate->format('Ymd');
                $businessPrefix = str_pad($business->id, 2, '0', STR_PAD_LEFT);
                $orderSequence = str_pad($i + 1, 3, '0', STR_PAD_LEFT);
                $orderNumber = "SO-{$date}-{$businessPrefix}{$orderSequence}";
                $salesOrder->update(['order_number' => $orderNumber]);

                // Add 1-3 items to each order
                $subTotal = 0;
                $availableProducts = collect($products)->filter(function($product) {
                    return $product->quantity > 0;
                });
                
                if ($availableProducts->count() > 0) {
                    $selectedProducts = $availableProducts->random(rand(1, min(3, $availableProducts->count())));
                    
                    foreach ($selectedProducts as $product) {
                        $maxQuantity = min($product->quantity, rand(1, 10));
                        $quantity = rand(1, $maxQuantity);
                        $unitPrice = $product->selling_price;
                        $totalPrice = $quantity * $unitPrice;
                        $subTotal += $totalPrice;

                        $quantityShipped = 0;
                        $quantityDelivered = 0;

                        // Set shipped/delivered quantities based on status
                        if ($status === 'partial') {
                            $quantityShipped = rand(1, $quantity);
                            $quantityDelivered = rand(0, $quantityShipped);
                        } elseif ($status === 'completed') {
                            $quantityShipped = $quantity;
                            $quantityDelivered = $quantity;
                        }

                        SalesOrderItem::create([
                            'sales_order_id' => $salesOrder->id,
                            'product_id' => $product->id,
                            'quantity_ordered' => $quantity,
                            'quantity_shipped' => $quantityShipped,
                            'quantity_delivered' => $quantityDelivered,
                            'unit_price' => $unitPrice,
                            'total_price' => $totalPrice,
                            'created_by' => $admin->id,
                        ]);

                        // Update product stock for shipped items
                        if ($quantityShipped > 0) {
                            $quantityBefore = $product->quantity;
                            $product->decrement('quantity', $quantityShipped);

                            // Create inventory history
                            InventoryHistory::create([
                                'business_id' => $business->id,
                                'product_id' => $product->id,
                                'user_id' => $admin->id,
                                'type' => 'stock-out',
                                'quantity_change' => -$quantityShipped,
                                'quantity_before' => $quantityBefore,
                                'quantity_after' => $quantityBefore - $quantityShipped,
                                'reason' => "Sales Order Shipped: {$salesOrder->order_number}",
                                'reference_type' => SalesOrder::class,
                                'reference_id' => $salesOrder->id,
                            ]);
                        }
                    }
                }

                // Update order totals
                $discountAmount = $salesOrder->discount;
                $afterDiscount = $subTotal - $discountAmount;
                $taxAmount = ($afterDiscount * 8.5) / 100;
                $totalAmount = $afterDiscount + $taxAmount;
                
                $salesOrder->update([
                    'sub_total' => $subTotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                ]);

                // Create payments based on status
                $paidAmount = 0;
                if ($status === 'completed') {
                    $paidAmount = $totalAmount;
                } elseif ($status === 'partial') {
                    $paidAmount = $totalAmount * (rand(30, 80) / 100);
                } else {
                    $paidAmount = rand(0, 1) ? $totalAmount * (rand(10, 40) / 100) : 0;
                }
                
                if ($paidAmount > 0) {
                    $payment = Payment::create([
                        'business_id' => $business->id,
                        'paymentable_type' => SalesOrder::class,
                        'paymentable_id' => $salesOrder->id,
                        'payment_method_id' => $paymentMethods[array_rand($paymentMethods)]->id,
                        'amount' => $paidAmount,
                        'transaction_date' => $orderDate->copy()->addDays(rand(0, 5)),
                        'status' => 'clear',
                        'reference_number' => 'REC-' . strtoupper(uniqid()),
                        'details' => 'Payment for sales order',
                        'created_by' => $admin->id,
                    ]);

                    $salesOrder->update(['paid_amount' => $paidAmount]);

                    // Update customer balance
                    $remainingDue = $totalAmount - $paidAmount;
                    if ($remainingDue > 0) {
                        $customer->refresh(); // Refresh to get current balance
                        $previousBalance = $customer->current_balance;
                        $customer->decrement('current_balance', $remainingDue);
                        
                        UserBalance::create([
                            'business_id' => $business->id,
                            'user_id' => $customer->id,
                            'balance_type' => 'debit',
                            'amount' => $remainingDue,
                            'previous_balance' => $previousBalance,
                            'new_balance' => $previousBalance - $remainingDue,
                            'transaction_date' => $orderDate,
                            'description' => "Outstanding amount for Sales Order {$salesOrder->order_number}",
                            'balanceable_type' => SalesOrder::class,
                            'balanceable_id' => $salesOrder->id,
                            'created_by' => $admin->id,
                        ]);
                    } elseif ($paidAmount > $totalAmount) {
                        // Overpayment creates credit for customer
                        $overpayment = $paidAmount - $totalAmount;
                        $customer->refresh(); // Refresh to get current balance
                        $previousBalance = $customer->current_balance;
                        $customer->increment('current_balance', $overpayment);
                        
                        UserBalance::create([
                            'business_id' => $business->id,
                            'user_id' => $customer->id,
                            'balance_type' => 'credit',
                            'amount' => $overpayment,
                            'previous_balance' => $previousBalance,
                            'new_balance' => $previousBalance + $overpayment,
                            'transaction_date' => $orderDate,
                            'description' => "Overpayment for Sales Order {$salesOrder->order_number}",
                            'balanceable_type' => SalesOrder::class,
                            'balanceable_id' => $salesOrder->id,
                            'created_by' => $admin->id,
                        ]);
                    }
                }

                // Generate invoice for completed/partial/shipped orders
                if (in_array($status, ['partial', 'completed', 'shipped'])) {
                    // Manually create unique invoice number for seeding
                    $date = $orderDate->format('Ymd');
                    $businessPrefix = str_pad($business->id, 2, '0', STR_PAD_LEFT);
                    $orderSequence = str_pad($i + 1, 3, '0', STR_PAD_LEFT);
                    $invoiceNumber = "INV-{$date}-{$businessPrefix}{$orderSequence}";
                    $salesOrder->update(['invoice_number' => $invoiceNumber]);
                }
            }
        }
    }

    private function createExpenses(): void
    {
        $this->command->info('💸 Creating expenses...');
        
        foreach ($this->businesses as $business) {
            $categories = $this->expenseCategories[$business->id];
            $paymentMethods = $this->paymentMethods[$business->id];
            $admin = $this->users[$business->id]['admin'];

            // Create 8-12 expenses per business
            for ($i = 0; $i < rand(8, 12); $i++) {
                $category = $categories[array_rand($categories)];
                $expenseDate = Carbon::now()->subDays(rand(1, 90));
                
                $expenseData = [
                    'Office Supplies' => ['amount' => rand(50, 300), 'description' => 'Purchased office stationery and supplies'],
                    'Utilities' => ['amount' => rand(200, 800), 'description' => 'Monthly utility bills payment'],
                    'Marketing' => ['amount' => rand(500, 2000), 'description' => 'Online advertising campaign'],
                    'Transportation' => ['amount' => rand(100, 500), 'description' => 'Vehicle fuel and maintenance'],
                    'Equipment' => ['amount' => rand(1000, 5000), 'description' => 'New equipment purchase'],
                    'Professional Services' => ['amount' => rand(300, 1500), 'description' => 'Legal consultation fees'],
                ];

                $data = $expenseData[$category->name] ?? ['amount' => rand(100, 1000), 'description' => 'General business expense'];

                Expense::create([
                    'business_id' => $business->id,
                    'expense_category_id' => $category->id,
                    'user_id' => $admin->id,
                    'title' => $category->name . ' Expense',
                    'amount' => $data['amount'],
                    'expense_date' => $expenseDate,
                    'description' => $data['description'],
                    'reference_number' => 'REC-' . strtoupper(uniqid()),
                    'created_by' => $admin->id,
                ]);
            }
        }
    }

    private function createEmployeeSalaries(): void
    {
        $this->command->info('💼 Creating employee salaries...');
        
        foreach ($this->businesses as $business) {
            if (!isset($this->users[$business->id]['staff'])) continue;
            
            $staff = $this->users[$business->id]['staff'];
            $admin = $this->users[$business->id]['admin'];

            foreach ($staff as $employee) {
                // Create salary records for the last 3 months
                for ($month = 3; $month >= 1; $month--) {
                    $salaryMonth = Carbon::now()->subMonths($month)->startOfMonth();
                    $paymentDate = $salaryMonth->copy()->endOfMonth()->subDays(rand(0, 5));
                    
                    EmployeeSalary::create([
                        'business_id' => $business->id,
                        'employee_id' => $employee->id,
                        'amount' => $employee->salary_amount ?? rand(3000, 8000),
                        'type' => 'salary',
                        'payment_date' => $paymentDate,
                        'salary_month' => $salaryMonth,
                        'notes' => 'Monthly salary payment',
                        'reference_number' => 'SAL-' . $paymentDate->format('Ym') . '-' . $employee->id,
                        'status' => 'paid',
                        'created_by' => $admin->id,
                    ]);

                    // Occasionally add bonuses or advances
                    if (rand(1, 4) === 1) {
                        $bonusAmount = rand(500, 2000);
                        EmployeeSalary::create([
                            'business_id' => $business->id,
                            'employee_id' => $employee->id,
                            'amount' => $bonusAmount,
                            'type' => 'bonus',
                            'payment_date' => $paymentDate->copy()->addDays(rand(1, 5)),
                            'salary_month' => $salaryMonth,
                            'notes' => 'Performance bonus',
                            'reference_number' => 'BON-' . strtoupper(uniqid()),
                            'status' => 'paid',
                            'created_by' => $admin->id,
                        ]);
                    }
                }
            }
        }
    }

    private function createInvestors(): void
    {
        $this->command->info('💰 Creating investors...');
        
        foreach ($this->businesses as $business) {
            $admin = $this->users[$business->id]['admin'];
            
            // Create 2-3 investors per business
            for ($i = 0; $i < rand(2, 3); $i++) {
                $investmentDate = Carbon::now()->subDays(rand(30, 365));
                $maturityDate = $investmentDate->copy()->addDays(rand(180, 730));
                
                $investorNames = [
                    'Strategic Capital Partners',
                    'Growth Investment Fund',
                    'Angel Investor Group',
                    'Venture Capital LLC',
                    'Family Investment Trust'
                ];

                Investor::create([
                    'business_id' => $business->id,
                    'name' => $investorNames[array_rand($investorNames)] . ' ' . ($i + 1),
                    'phone' => '+1-600-' . str_pad($business->id . ($i + 1) . '00', 4, '0'),
                    'nid' => 'NID' . str_pad($business->id . ($i + 1) . rand(100, 999), 10, '0'),
                    'address' => 'Investment District, City ' . ($i + 1),
                    'investment_amount' => rand(10000, 100000),
                    'profit_return' => rand(1000, 5000),
                    'profit_rate' => rand(8, 15),
                    'investment_date' => $investmentDate,
                    'close_date' => $maturityDate,
                    'status' => rand(0, 1) ? 'active' : 'active', // Most active
                    'notes' => 'Test investor for business development',
                    'created_by' => $admin->id,
                ]);
            }
        }
    }

    private function createUserBalanceAdjustments(): void
    {
        $this->command->info('⚖️ Creating user balance adjustments...');
        
        foreach ($this->businesses as $business) {
            $admin = $this->users[$business->id]['admin'];
            
            // Create some balance adjustments for suppliers and customers
            if (isset($this->users[$business->id]['suppliers'])) {
                foreach ($this->users[$business->id]['suppliers'] as $supplier) {
                    if (rand(1, 3) === 1) { // 33% chance
                        $adjustmentAmount = rand(100, 1000);
                        $balanceType = rand(0, 1) ? 'credit' : 'debit';
                        $supplier->refresh(); // Refresh to get current balance
                        $previousBalance = $supplier->current_balance;
                        
                        UserBalance::create([
                            'business_id' => $business->id,
                            'user_id' => $supplier->id,
                            'balance_type' => $balanceType,
                            'amount' => $adjustmentAmount,
                            'previous_balance' => $previousBalance,
                            'new_balance' => $balanceType === 'credit' ? 
                                $previousBalance + $adjustmentAmount : 
                                $previousBalance - $adjustmentAmount,
                            'transaction_date' => Carbon::now()->subDays(rand(1, 30)),
                            'description' => 'Manual balance adjustment - ' . ucfirst($balanceType),
                            'reference_number' => 'ADJ-' . strtoupper(uniqid()),
                            'balanceable_type' => User::class,
                            'balanceable_id' => $supplier->id,
                            'created_by' => $admin->id,
                        ]);

                        // Update user's current balance
                        if ($balanceType === 'credit') {
                            $supplier->increment('current_balance', $adjustmentAmount);
                        } else {
                            $supplier->decrement('current_balance', $adjustmentAmount);
                        }
                    }
                }
            }

            if (isset($this->users[$business->id]['buyers'])) {
                foreach ($this->users[$business->id]['buyers'] as $customer) {
                    if (rand(1, 4) === 1) { // 25% chance
                        $adjustmentAmount = rand(50, 500);
                        $balanceType = rand(0, 1) ? 'credit' : 'debit';
                        $customer->refresh(); // Refresh to get current balance
                        $previousBalance = $customer->current_balance;
                        
                        UserBalance::create([
                            'business_id' => $business->id,
                            'user_id' => $customer->id,
                            'balance_type' => $balanceType,
                            'amount' => $adjustmentAmount,
                            'previous_balance' => $previousBalance,
                            'new_balance' => $balanceType === 'credit' ? 
                                $previousBalance + $adjustmentAmount : 
                                $previousBalance - $adjustmentAmount,
                            'transaction_date' => Carbon::now()->subDays(rand(1, 20)),
                            'description' => 'Manual balance adjustment - ' . ucfirst($balanceType),
                            'reference_number' => 'ADJ-' . strtoupper(uniqid()),
                            'balanceable_type' => User::class,
                            'balanceable_id' => $customer->id,
                            'created_by' => $admin->id,
                        ]);

                        // Update user's current balance
                        if ($balanceType === 'credit') {
                            $customer->increment('current_balance', $adjustmentAmount);
                        } else {
                            $customer->decrement('current_balance', $adjustmentAmount);
                        }
                    }
                }
            }
        }
    }

    private function printSummary(): void
    {
        $this->command->info('');
        $this->command->info('📊 SEEDING SUMMARY:');
        $this->command->info('==================');
        
        foreach ($this->businesses as $business) {
            $this->command->info("🏢 Business: {$business->name}");
            $this->command->info("   📧 Admin Email: admin@" . strtolower(str_replace(' ', '', $business->name)) . ".com");
            $this->command->info("   🔑 Password: password123");
            
            // Count data for this business
            $userCounts = [
                'staff' => isset($this->users[$business->id]['staff']) ? count($this->users[$business->id]['staff']) : 0,
                'suppliers' => isset($this->users[$business->id]['suppliers']) ? count($this->users[$business->id]['suppliers']) : 0,
                'customers' => isset($this->users[$business->id]['buyers']) ? count($this->users[$business->id]['buyers']) : 0,
                'others' => isset($this->users[$business->id]['others']) ? count($this->users[$business->id]['others']) : 0,
            ];
            
            $this->command->info("   👥 Users: " . 
                "{$userCounts['staff']} staff, " .
                "{$userCounts['suppliers']} suppliers, " .
                "{$userCounts['customers']} customers, " .
                "{$userCounts['others']} others"
            );
            
            $productCount = isset($this->products[$business->id]) ? count($this->products[$business->id]) : 0;
            $this->command->info("   📦 Products: {$productCount}");
            
            $purchaseOrderCount = PurchaseOrder::where('business_id', $business->id)->count();
            $salesOrderCount = SalesOrder::where('business_id', $business->id)->count();
            $this->command->info("   📋 Orders: {$purchaseOrderCount} purchase, {$salesOrderCount} sales");
            
            $expenseCount = Expense::where('business_id', $business->id)->count();
            $this->command->info("   💸 Expenses: {$expenseCount}");
            
            $this->command->info('');
        }
        
        $this->command->info('🎯 TEST DATA READY FOR:');
        $this->command->info('- User Balance Summary (grouped by user_type)');
        $this->command->info('- Sales & Purchase Reports (with date ranges)');
        $this->command->info('- Inventory Reports (with stock movements)');
        $this->command->info('- Profit & Loss Reports');
        $this->command->info('- Employee Management (with salary data)');
        $this->command->info('- Product Management (with inventory tracking)');
        $this->command->info('- Order Management (purchase & sales workflows)');
        $this->command->info('');
        $this->command->info('🚀 Ready to test all enhanced APIs!');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExpenseCategory;

use App\Models\Business;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = Business::all();

        if ($businesses->isEmpty()) {
            $this->command->warn('No businesses found. Please run BusinessSeeder first.');
            return;
        }

        $categories = [
            ['name' => 'Office Rent', 'description' => 'Monthly office rent and lease payments'],
            ['name' => 'Utilities', 'description' => 'Electricity, water, gas, internet, etc.'],
            ['name' => 'Salaries & Wages', 'description' => 'Employee salaries, wages, and benefits'],
            ['name' => 'Stationery & Office Supplies', 'description' => 'Paper, pens, printer ink, etc.'],
            ['name' => 'Maintenance & Repairs', 'description' => 'Repairs and maintenance of office and equipment'],
            ['name' => 'Marketing & Advertising', 'description' => 'Promotional and marketing expenses'],
            ['name' => 'Travel & Transportation', 'description' => 'Business travel, fuel, and transport costs'],
            ['name' => 'Legal & Professional Fees', 'description' => 'Consulting, legal, and professional services'],
            ['name' => 'Insurance', 'description' => 'Business insurance premiums'],
            ['name' => 'Miscellaneous', 'description' => 'Other uncategorized expenses'],
        ];

        foreach ($businesses as $business) {
            foreach ($categories as $category) {
                ExpenseCategory::firstOrCreate([
                    'business_id' => $business->id,
                    'name' => $category['name'],
                ], [
                    'business_id' => $business->id,
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Expense categories seeded for all businesses!');
    }
}

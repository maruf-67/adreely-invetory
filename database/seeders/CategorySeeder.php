<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
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
            'Electronics',
            'Clothing',
            'Home & Garden',
            'Sports & Outdoors',
            'Books',
            'Toys & Games',
            'Health & Beauty',
            'Automotive',
            'Food & Beverages',
            'Office Supplies',
            'Computers & Accessories',
            'Mobile Phones',
            'Kitchen & Dining',
            'Furniture',
            'Tools & Hardware',
        ];

        foreach ($businesses as $business) {
            foreach ($categories as $categoryName) {
                Category::create([
                    'business_id' => $business->id,
                    'name' => $categoryName,
                    'description' => "Description for {$categoryName}",
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Categories seeded successfully!');
    }
}

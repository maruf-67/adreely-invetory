<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
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

        $faker = Faker::create();

        // Sample products data
        $productTemplates = [
            // Electronics
            ['name' => 'iPhone 15 Pro', 'category' => 'Electronics', 'brand' => 'Apple', 'unit' => 'Piece'],
            ['name' => 'Samsung Galaxy S24', 'category' => 'Electronics', 'brand' => 'Samsung', 'unit' => 'Piece'],
            ['name' => 'MacBook Pro M3', 'category' => 'Computers & Accessories', 'brand' => 'Apple', 'unit' => 'Piece'],
            ['name' => 'Dell XPS 13', 'category' => 'Computers & Accessories', 'brand' => 'Dell', 'unit' => 'Piece'],
            ['name' => 'Sony WH-1000XM5', 'category' => 'Electronics', 'brand' => 'Sony', 'unit' => 'Piece'],
            
            // Clothing
            ['name' => 'Nike Air Max 90', 'category' => 'Clothing', 'brand' => 'Nike', 'unit' => 'Pair'],
            ['name' => 'Adidas Ultraboost 22', 'category' => 'Clothing', 'brand' => 'Adidas', 'unit' => 'Pair'],
            ['name' => 'Nike Dri-FIT T-Shirt', 'category' => 'Clothing', 'brand' => 'Nike', 'unit' => 'Piece'],
            
            // Home & Garden
            ['name' => 'Philips LED Bulb 9W', 'category' => 'Home & Garden', 'brand' => 'Philips', 'unit' => 'Piece'],
            ['name' => 'Bosch Drill Machine', 'category' => 'Tools & Hardware', 'brand' => 'Bosch', 'unit' => 'Piece'],
            
            // Food & Beverages
            ['name' => 'Coca-Cola 500ml', 'category' => 'Food & Beverages', 'brand' => 'Coca-Cola', 'unit' => 'Bottle'],
            ['name' => 'Pepsi 500ml', 'category' => 'Food & Beverages', 'brand' => 'Pepsi', 'unit' => 'Bottle'],
            ['name' => 'Nestle Coffee 200g', 'category' => 'Food & Beverages', 'brand' => 'Nestle', 'unit' => 'Pack'],
            
            // Office Supplies
            ['name' => 'HP LaserJet Printer', 'category' => 'Office Supplies', 'brand' => 'HP', 'unit' => 'Piece'],
            ['name' => 'Canon DSLR Camera', 'category' => 'Electronics', 'brand' => 'Canon', 'unit' => 'Piece'],
            
            // Kitchen & Dining
            ['name' => 'Panasonic Microwave', 'category' => 'Kitchen & Dining', 'brand' => 'Panasonic', 'unit' => 'Piece'],
            ['name' => 'LG Refrigerator', 'category' => 'Kitchen & Dining', 'brand' => 'LG', 'unit' => 'Piece'],
            
            // Automotive
            ['name' => 'Toyota Engine Oil 5W-30', 'category' => 'Automotive', 'brand' => 'Toyota', 'unit' => 'Liter'],
            ['name' => 'Honda Brake Pads', 'category' => 'Automotive', 'brand' => 'Honda', 'unit' => 'Set'],
            
            // Health & Beauty
            ['name' => 'Unilever Shampoo 400ml', 'category' => 'Health & Beauty', 'brand' => 'Unilever', 'unit' => 'Bottle'],
            ['name' => 'P&G Toothpaste 100g', 'category' => 'Health & Beauty', 'brand' => 'P&G', 'unit' => 'Pack'],
        ];

        foreach ($businesses as $business) {
            $categories = Category::where('business_id', $business->id)->get();
            $brands = Brand::where('business_id', $business->id)->get();
            $units = Unit::where('business_id', $business->id)->get();

            if ($categories->isEmpty() || $brands->isEmpty() || $units->isEmpty()) {
                $this->command->warn("Missing categories, brands, or units for business {$business->name}. Please run CategorySeeder, BrandSeeder, and UnitSeeder first.");
                continue;
            }

            foreach ($productTemplates as $template) {
                // Find matching category, brand, and unit
                $category = $categories->where('name', $template['category'])->first();
                $brand = $brands->where('name', $template['brand'])->first();
                $unit = $units->where('name', $template['unit'])->first();

                // If any required relation doesn't exist, use random ones
                if (!$category) $category = $categories->random();
                if (!$brand) $brand = $brands->random();
                if (!$unit) $unit = $units->random();

                $purchasePrice = $faker->randomFloat(2, 10, 500);
                $sellingPrice = $purchasePrice * $faker->randomFloat(2, 1.2, 2.5); // 20% to 150% markup

                Product::create([
                    'business_id' => $business->id,
                    'name' => $template['name'],
                    'sku' => 'SKU-' . strtoupper($faker->bothify('???###')),
                    'description' => $faker->sentence(10),
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'unit_id' => $unit->id,
                    'purchase_price' => $purchasePrice,
                    'selling_price' => $sellingPrice,
                    'quantity' => $faker->numberBetween(0, 100),
                    'low_stock_threshold' => $faker->numberBetween(5, 20),
                ]);
            }

            // Create some additional random products
            for ($i = 0; $i < 30; $i++) {
                $purchasePrice = $faker->randomFloat(2, 5, 1000);
                $sellingPrice = $purchasePrice * $faker->randomFloat(2, 1.1, 3.0);

                Product::create([
                    'business_id' => $business->id,
                    'name' => $faker->words(2, true) . ' ' . $faker->word,
                    'sku' => 'SKU-' . strtoupper($faker->bothify('???###')),
                    'description' => $faker->sentence(15),
                    'category_id' => $categories->random()->id,
                    'brand_id' => $brands->random()->id,
                    'unit_id' => $units->random()->id,
                    'purchase_price' => $purchasePrice,
                    'selling_price' => $sellingPrice,
                    'quantity' => $faker->numberBetween(0, 200),
                    'low_stock_threshold' => $faker->numberBetween(5, 25),
                ]);
            }
        }

        $this->command->info('Products seeded successfully!');
    }
}

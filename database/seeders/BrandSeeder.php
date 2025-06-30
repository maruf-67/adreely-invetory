<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
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

        $brands = [
            'Apple',
            'Samsung',
            'Nike',
            'Adidas',
            'Sony',
            'LG',
            'HP',
            'Dell',
            'Canon',
            'Nikon',
            'Microsoft',
            'Google',
            'Amazon',
            'Philips',
            'Panasonic',
            'Bosch',
            'Siemens',
            'Toyota',
            'Honda',
            'Ford',
            'Coca-Cola',
            'Pepsi',
            'Nestle',
            'Unilever',
            'P&G',
        ];

        foreach ($businesses as $business) {
            foreach ($brands as $brandName) {
                Brand::create([
                    'business_id' => $business->id,
                    'name' => $brandName,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Brands seeded successfully!');
    }
}

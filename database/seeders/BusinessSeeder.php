<?php

namespace Database\Seeders;

use App\Models\Business;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = [
            [
                'name' => 'Tech Solutions Ltd',
                'address' => '123 Business Park, Tech City',
                'phone' => '+1234567890',
                'email' => 'contact@techsolutions.com',
                'description' => 'A technology solutions company',
                'is_active' => true,
            ],
            [
                'name' => 'Green Foods Co',
                'address' => '456 Organic Street, Green Valley',
                'phone' => '+1234567891',
                'email' => 'info@greenfoods.com',
                'description' => 'Organic food distribution company',
                'is_active' => true,
            ],
        ];

        foreach ($businesses as $businessData) {
            Business::create($businessData);
        }
    }
}

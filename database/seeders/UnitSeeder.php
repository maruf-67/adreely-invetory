<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
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

        $units = [
            ['name' => 'Piece', 'short_name' => 'pcs'],
            ['name' => 'Kilogram', 'short_name' => 'kg'],
            ['name' => 'Gram', 'short_name' => 'g'],
            ['name' => 'Liter', 'short_name' => 'l'],
            ['name' => 'Milliliter', 'short_name' => 'ml'],
            ['name' => 'Meter', 'short_name' => 'm'],
            ['name' => 'Centimeter', 'short_name' => 'cm'],
            ['name' => 'Box', 'short_name' => 'box'],
            ['name' => 'Pack', 'short_name' => 'pack'],
            ['name' => 'Dozen', 'short_name' => 'dz'],
            ['name' => 'Pair', 'short_name' => 'pair'],
            ['name' => 'Set', 'short_name' => 'set'],
            ['name' => 'Bundle', 'short_name' => 'bundle'],
            ['name' => 'Roll', 'short_name' => 'roll'],
            ['name' => 'Sheet', 'short_name' => 'sheet'],
            ['name' => 'Bottle', 'short_name' => 'btl'],
            ['name' => 'Can', 'short_name' => 'can'],
            ['name' => 'Bag', 'short_name' => 'bag'],
            ['name' => 'Carton', 'short_name' => 'ctn'],
            ['name' => 'Unit', 'short_name' => 'unit'],
        ];

        foreach ($businesses as $business) {
            foreach ($units as $unitData) {
                Unit::create([
                    'business_id' => $business->id,
                    'name' => $unitData['name'],
                    'short_name' => $unitData['short_name'],
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Units seeded successfully!');
    }
}

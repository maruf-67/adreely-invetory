<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Business;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businesses = Business::all();

        foreach ($businesses as $business) {
            // Create admin user for each business
            $admin = User::create([
                'business_id' => $business->id,
                'name' => 'Admin User',
                'phone' => '+123456789' . $business->id,
                'email' => 'admin' . $business->id . '@example.com',
                'password' => Hash::make('password123'),
                'user_type' => 'admin',
                'current_balance' => 0,
            ]);

            // Update business owner
            $business->update(['owner_id' => $admin->id]);

            // Create staff user
            User::create([
                'business_id' => $business->id,
                'name' => 'Staff User',
                'phone' => '+123456788' . $business->id,
                'email' => 'staff' . $business->id . '@example.com',
                'password' => Hash::make('password123'),
                'user_type' => 'staff',
                'current_balance' => 0,
                'created_by' => $admin->id,
            ]);

            // Create some suppliers
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

            // Create some retailers
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

            // Create some dealers
            User::create([
                'business_id' => $business->id,
                'name' => 'Dealer Network ' . $business->id,
                'phone' => '+123456785' . $business->id,
                'user_type' => 'dealer',
                'party_type' => 'Regular',
                'current_balance' => 0,
                'created_by' => $admin->id,
            ]);
        }
    }
}

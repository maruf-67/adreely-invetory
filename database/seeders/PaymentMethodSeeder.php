<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all businesses and create default payment methods for each
        $businesses = Business::all();

        foreach ($businesses as $business) {
            $paymentMethods = [
                [
                    'name' => 'Cash',
                    'type' => 'cash',
                    'details' => null,
                    'is_active' => true,
                ],
                [
                    'name' => 'Bank Transfer',
                    'type' => 'bank_transfer',
                    'details' => [
                        'bank_name' => 'Sample Bank',
                        'account_number' => '1234567890',
                        'account_name' => $business->name,
                    ],
                    'is_active' => true,
                ],
                [
                    'name' => 'Cheque',
                    'type' => 'cheque',
                    'details' => null,
                    'is_active' => true,
                ],
                [
                    'name' => 'Mobile Banking',
                    'type' => 'mobile_banking',
                    'details' => [
                        'service' => 'bKash',
                        'number' => '01700000000',
                    ],
                    'is_active' => true,
                ],
            ];

            foreach ($paymentMethods as $method) {
                PaymentMethod::create([
                    'business_id' => $business->id,
                    'name' => $method['name'],
                    'type' => $method['type'],
                    'details' => $method['details'],
                    'is_active' => $method['is_active'],
                    'created_by' => $business->owner_id,
                ]);
            }
        }
    }
}

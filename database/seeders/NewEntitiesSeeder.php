<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Business;
use App\Models\Investor;
use App\Models\Investment;
use App\Models\ExtraIncomeType;
use App\Models\ExtraIncome;
use Carbon\Carbon;

class NewEntitiesSeeder extends Seeder
{
    public function run(): void
    {
        $businesses = Business::all();

        foreach ($businesses as $business) {
            // Create Investors
            $investors = [];
            for ($i = 1; $i <= 3; $i++) {
                $investor = Investor::create([
                    'business_id' => $business->id,
                    'name' => "Investor $i for {$business->name}",
                    'contact_info' => json_encode([
                        'email' => "investor{$i}@" . str_replace(' ', '', strtolower($business->name)) . ".com",
                        'phone' => "+123456789{$i}",
                        'address' => "123 Investor Street, City {$i}",
                        'notes' => "Notes for investor $i"
                    ])
                ]);
                $investors[] = $investor;
            }

            // Create Investments
            foreach ($investors as $investor) {
                for ($j = 1; $j <= 2; $j++) {
                    Investment::create([
                        'business_id' => $business->id,
                        'investor_id' => $investor->id,
                        'amount' => rand(10000, 100000),
                        'investment_date' => Carbon::now()->subDays(rand(1, 365)),
                        'status' => ['active', 'closed'][rand(0, 1)],
                    ]);
                }
            }

            // Create Extra Income Types
            $incomeTypes = [
                ['name' => 'Consulting Services'],
                ['name' => 'Interest Income'],
                ['name' => 'Rental Income'],
                ['name' => 'Commission Income'],
                ['name' => 'Grant Income'],
            ];

            $createdIncomeTypes = [];
            foreach ($incomeTypes as $typeData) {
                $incomeType = ExtraIncomeType::create([
                    'business_id' => $business->id,
                    'name' => $typeData['name']
                ]);
                $createdIncomeTypes[] = $incomeType;
            }

            // Create Extra Incomes
            foreach ($createdIncomeTypes as $incomeType) {
                for ($k = 1; $k <= rand(2, 4); $k++) {
                    ExtraIncome::create([
                        'business_id' => $business->id,
                        'income_type_id' => $incomeType->id,
                        'amount' => rand(1000, 25000),
                        'date' => Carbon::now()->subDays(rand(1, 180)),
                        'description' => "Income from {$incomeType->name} - Entry $k"
                    ]);
                }
            }
        }

        $this->command->info('New entities seeded successfully!');
    }
}

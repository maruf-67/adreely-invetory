<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting Database Seeding...');
        
        // Ask for seeding type
        $useComprehensive = $this->command->choice(
            'Which seeder would you like to run?',
            [
                'comprehensive' => 'Comprehensive Test Data (Recommended for API testing)',
                'basic' => 'Basic Seeders (Original)',
            ],
            'comprehensive'
        );

        if ($useComprehensive === 'comprehensive') {
            if ($this->command->confirm('This will create comprehensive test data for all APIs. Continue?', true)) {
                $this->call([
                    ComprehensiveTestDataSeeder::class,
                ]);
                
                $this->command->info('');
                $this->command->info('✅ Comprehensive seeding completed successfully!');
                $this->command->info('');
                $this->command->info('🔗 Quick Access:');
                $this->command->info('   TechVibe Admin: admin@techvibeelectronics.com');
                $this->command->info('   FreshMart Admin: admin@freshmartgrocery.com');
                $this->command->info('   AutoParts Admin: admin@autopartscentral.com');
                $this->command->info('   Password for all: password123');
            } else {
                $this->command->info('❌ Seeding cancelled.');
            }
        } else {
            // Call basic seeders in order
            $this->call([
                BusinessSeeder::class,
                UserSeeder::class,
                CategorySeeder::class,
                BrandSeeder::class,
                UnitSeeder::class,
                ProductSeeder::class,
                // RolePermissionSeeder::class,
            ]);
            
            $this->command->info('✅ Basic seeding completed!');
        }
    }
}

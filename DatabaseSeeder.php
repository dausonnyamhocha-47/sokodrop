<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder
 *
 * Entry point for `php artisan db:seed`. Order matters: AdminUserSeeder
 * runs first since every environment needs at least one Admin account to
 * approve merchants/shoppers, then DemoDataSeeder populates a realistic
 * Kariakoo-flavored dataset (merchant + shop + products + shopper + VIP +
 * a sample order) so the frontend has something to render immediately
 * after a fresh `migrate:fresh --seed`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * AdminUserSeeder
 *
 * Every Sokodrop deployment needs at least one Admin to approve merchants
 * and shoppers (see Admin\VerificationController) — without this seeder,
 * a fresh database has no way to bootstrap that first Admin account short
 * of manually inserting a row. Uses firstOrCreate so re-running `db:seed`
 * is idempotent and never creates duplicate admins.
 *
 * IMPORTANT: change this password immediately in any non-local environment
 * — it exists here purely to unblock local development.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@sokodrop.co.tz'],
            [
                'name' => 'Sokodrop Admin',
                'phone' => '+255700000000',
                'password' => 'ChangeMe123!', // 'hashed' cast on User model hashes this automatically
                'role' => User::ROLE_ADMIN,
                'is_verified' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
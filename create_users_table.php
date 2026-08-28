<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users table.
 *
 * This single table backs all four platform roles (vip, merchant, shopper, admin)
 * distinguished by the `role` enum column. This avoids a fragmented multi-table
 * auth setup while still allowing role-specific data to live in dedicated
 * "profile" tables (see ShopperProfile, Shop) that hang off this table via
 * a one-to-one foreign key relationship.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->unique();
            $table->string('password');

            // Role drives which profile table (if any) is attached to this user
            // and which middleware/policies apply (see CheckRole middleware).
            $table->enum('role', ['vip', 'merchant', 'shopper', 'admin'])->default('vip');

            // NIDA (National Identification Authority) number, required for
            // KYC verification of merchants and shoppers. Nullable because VIP
            // clients may operate with lighter-touch verification for discretion.
            $table->string('nida_number', 30)->nullable()->unique();

            // General verification flag set by Admin\VerificationController
            // after KYC/NIDA checks pass. Distinct from ShopperProfile's more
            // granular verification_status because merchants/admins also use this.
            $table->boolean('is_verified')->default(false);

            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); // Preserve historical order/transaction integrity

            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shops table.
 *
 * Represents a single Kariakoo merchant stall. A merchant `user` (role =
 * 'merchant') hasOne shop (1:1 in the current business rule — one stall
 * per merchant account). Kept as its own table (rather than folded into
 * users) because it carries physical-location and business-registration
 * data that has its own lifecycle (e.g. re-approval on stall transfer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();

            $table->foreignId('merchant_id')
                ->unique()
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('shop_name');
            $table->string('stall_number', 20); // Physical Kariakoo stall identifier
            $table->string('market_section')->nullable(); // e.g. "Mchikichini", "Muhoni"
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();

            // Business registration reference (BRELA/TIN) for compliance checks
            $table->string('business_license_number', 50)->nullable();

            // Admin gate: shop is invisible to VIP catalog until approved.
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_active')->default(true); // Merchant can pause without losing approval

            $table->timestamps();

            $table->index(['is_approved', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
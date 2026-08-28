<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shopper profiles table.
 *
 * Extends a `users` row (role = 'shopper') with delivery-agent-specific
 * KYC and operational data. Kept separate from `users` (1:1) rather than
 * adding shopper-only columns directly to `users` to keep the users table
 * lean for the other three roles and to make shopper-specific migrations
 * independently versionable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopper_profiles', function (Blueprint $table) {
            $table->id();

            // One shopper profile per user. Cascade delete: if the underlying
            // user account is removed, the profile is meaningless without it.
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->onDelete('cascade');

            // Duplicated here (in addition to users.nida_number) intentionally:
            // this is the *submitted document* reference for the shopper KYC
            // packet, which may differ in format from the primary NIDA number
            // stored on the user account (e.g. includes a physical ID scan ref).
            $table->string('id_number', 30);
            $table->string('id_document_path')->nullable();

            $table->enum('vehicle_type', ['bicycle', 'motorcycle', 'car', 'on_foot'])->default('on_foot');

            // Admin-controlled verification workflow distinct from users.is_verified
            // so a shopper can be "verified" as a general user but still pending
            // for the specific shopper KYC checks (background check, etc.).
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();

            // Decimal precision for rating average (e.g. 4.75) computed from
            // completed order feedback; kept denormalized here for fast reads
            // on shopper listing/assignment screens.
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->unsignedInteger('completed_deliveries')->default(0);

            $table->boolean('is_available')->default(false);

            $table->timestamps();

            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopper_profiles');
    }
};
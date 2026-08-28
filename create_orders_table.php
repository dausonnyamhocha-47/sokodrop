<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders table.
 *
 * Central record tying a VIP client to an assigned shopper, its line items,
 * and its financial breakdown. `subtotal_amount + delivery_fee + service_fee
 * = total_amount` is enforced in CommissionCalculator (rule #4 of the
 * master directive), not as a DB-level generated column, so that the
 * calculation logic (and any future promo/discount rules) stays in one
 * auditable service class rather than duplicated in SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // VIP client who placed the order.
            $table->foreignId('client_id')
                ->constrained('users')
                ->onDelete('restrict'); // Never allow deleting a user with order history

            // Shopper assigned to fulfill it. Nullable: order starts unassigned
            // in 'pending' status until a shopper accepts it.
            $table->foreignId('shopper_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->enum('status', [
                'pending',      // Placed, awaiting shopper acceptance
                'accepted',     // Shopper assigned
                'shopping',     // Shopper physically at Kariakoo picking items
                'delivering',   // En route to VIP client
                'completed',    // Delivered and confirmed
                'cancelled',
            ])->default('pending');

            // Money columns: decimal(10,2) throughout for exact precision,
            // never float, per master directive rule #4.
            $table->decimal('subtotal_amount', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('service_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);

            // Discretion features for VIP anonymity: delivery uses a label/
            // code rather than displaying the client's name to the shopper.
            $table->string('delivery_code', 10)->unique();
            $table->text('delivery_address');
            $table->text('delivery_notes')->nullable();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'shopper_id']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
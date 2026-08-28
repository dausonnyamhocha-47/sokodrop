<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactions table.
 *
 * One transaction per order (1:1), recording how the total_amount was
 * actually paid and split between the three financial stakeholders:
 * the merchant (product payout), the shopper (delivery payout), and the
 * platform (commission). Kept separate from `orders` because payment
 * state (e.g. retried/failed payments) has a different lifecycle from
 * order fulfillment state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->onDelete('cascade');

            $table->enum('payment_method', ['mobile_money', 'card', 'cash_on_delivery', 'bank_transfer'])
                ->default('mobile_money');

            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])
                ->default('pending');

            // Payout split — computed by CommissionCalculator from the
            // order's subtotal_amount, delivery_fee, and service_fee.
            // merchant_payout + shopper_payout + platform_commission
            // must always equal the order's total_amount.
            $table->decimal('merchant_payout', 10, 2)->default(0);
            $table->decimal('shopper_payout', 10, 2)->default(0);
            $table->decimal('platform_commission', 10, 2)->default(0);

            $table->string('transaction_reference', 100)->unique(); // Gateway/mobile-money ref
            $table->string('payment_gateway')->nullable(); // e.g. "M-Pesa", "Tigo Pesa", "Airtel Money"

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
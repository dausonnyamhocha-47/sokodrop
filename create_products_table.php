<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products table.
 *
 * Items listed for sale by a shop. All monetary values use decimal(10,2)
 * rather than float to guarantee exact arithmetic when this price is later
 * multiplied by quantity in OrderItem and summed into Order totals
 * (see CommissionCalculator service and rule #4 in the master directive).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')
                ->constrained('shops')
                ->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // e.g. "Grains", "Spices", "Electronics"

            // Base unit price. Never mutated retroactively on past orders —
            // OrderItem stores its own unit_price snapshot at time of purchase.
            $table->decimal('price', 10, 2);
            $table->string('unit', 20)->default('piece'); // e.g. "kg", "litre", "piece", "dozen"

            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('image_path')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['shop_id', 'is_active']);
            $table->fullText(['name', 'description']); // Powers VIP catalog search
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
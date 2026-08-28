<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order items table.
 *
 * Line items for an order. Critically, `unit_price` and `product_name`
 * are snapshotted at order time (not just referenced via product_id) so
 * that historical orders remain accurate even if a merchant later changes
 * the product's price or name. `subtotal` = unit_price * quantity, stored
 * (not computed) so CommissionCalculator can sum it without re-deriving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');

            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('restrict'); // Preserve referential history

            // Snapshot fields — decouple this line item from future edits
            // to the underlying product row.
            $table->string('product_name_snapshot');
            $table->decimal('unit_price', 10, 2);

            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 10, 2);

            // Concierge quality-check step: shopper inspects the physical item
            // at the Kariakoo stall before it is marked fit for delivery.
            $table->boolean('quality_checked')->default(false);
            $table->boolean('is_substituted')->default(false);
            $table->text('substitution_note')->nullable();

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
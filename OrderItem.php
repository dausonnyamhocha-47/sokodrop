<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrderItem model.
 *
 * A single line item within an Order. `product_name_snapshot` and
 * `unit_price` are frozen at order-creation time so that later edits to
 * the Product (price change, rename) never retroactively alter historical
 * orders or past commission calculations.
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name_snapshot',
        'unit_price',
        'quantity',
        'subtotal',
        'quality_checked',
        'is_substituted',
        'substitution_note',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
        'quality_checked' => 'boolean',
        'is_substituted' => 'boolean',
    ];

    /**
     * Keep subtotal consistent with unit_price * quantity whenever either
     * changes, rather than trusting callers to compute it correctly.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (OrderItem $item) {
            $item->subtotal = round((float) $item->unit_price * $item->quantity, 2);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
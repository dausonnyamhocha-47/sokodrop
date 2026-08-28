<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product model.
 *
 * A single item listed by a Shop. `price` is the *current* listing price;
 * once an order is placed, OrderItem snapshots it as `unit_price` so past
 * orders are unaffected by later price changes here.
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'description',
        'category',
        'price',
        'unit',
        'stock_quantity',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope: products that can currently be added to a VIP cart.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)->where('stock_quantity', '>', 0);
    }

    public function hasSufficientStock(int $quantity): bool
    {
        return $this->stock_quantity >= $quantity;
    }
}
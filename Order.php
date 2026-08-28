<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Order model.
 *
 * Central record joining a VIP client, an assigned shopper, its line items,
 * and its single Transaction. Money fields (subtotal_amount, delivery_fee,
 * service_fee, total_amount) are decimal-cast to avoid float rounding
 * errors — actual computation lives in CommissionCalculator, not here,
 * so the arithmetic rule (subtotal + delivery + service = total) has one
 * single source of truth.
 */
class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_SHOPPING = 'shopping';
    public const STATUS_DELIVERING = 'delivering';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'client_id',
        'shopper_id',
        'status',
        'subtotal_amount',
        'delivery_fee',
        'service_fee',
        'total_amount',
        'delivery_code',
        'delivery_address',
        'delivery_notes',
        'accepted_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'accepted_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Auto-generate a unique delivery code on creation. Used by shoppers
     * to identify a discreet handoff without ever seeing the VIP client's
     * name directly (supports the platform's anonymity/discretion promise).
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order) {
            if (empty($order->delivery_code)) {
                $order->delivery_code = strtoupper(Str::random(8));
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function shopper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shopper_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    /**
     * Recalculate subtotal_amount from current line items. Does not touch
     * delivery_fee/service_fee/total_amount — that composition is the
     * responsibility of CommissionCalculator so all money math stays
     * centralized in one auditable place.
     */
    public function recalculateSubtotal(): void
    {
        $this->subtotal_amount = $this->items()->sum('subtotal');
        $this->save();
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACCEPTED], true);
    }
}
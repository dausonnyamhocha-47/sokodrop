<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Transaction model.
 *
 * 1:1 with Order. Records payment state and the three-way payout split
 * (merchant_payout + shopper_payout + platform_commission) that
 * CommissionCalculator computes from the order's totals. Kept separate
 * from Order because payment lifecycle (retries, refunds) is distinct
 * from fulfillment lifecycle.
 */
class Transaction extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id',
        'payment_method',
        'payment_status',
        'merchant_payout',
        'shopper_payout',
        'platform_commission',
        'transaction_reference',
        'payment_gateway',
        'paid_at',
        'refunded_at',
    ];

    protected $casts = [
        'merchant_payout' => 'decimal:2',
        'shopper_payout' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Transaction $transaction) {
            if (empty($transaction->transaction_reference)) {
                $transaction->transaction_reference = 'SKD-' . strtoupper(Str::random(12));
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function markAsPaid(): void
    {
        $this->payment_status = self::STATUS_PAID;
        $this->paid_at = now();
        $this->save();
    }
}
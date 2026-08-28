<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ShopperProfile model.
 *
 * 1:1 extension of a User (role = shopper) holding KYC/verification and
 * operational stats (rating, availability). Kept separate from User so
 * that shopper-only fields don't pollute the base auth table.
 */
class ShopperProfile extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'id_number',
        'id_document_path',
        'vehicle_type',
        'verification_status',
        'rejection_reason',
        'rating',
        'completed_deliveries',
        'is_available',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'completed_deliveries' => 'integer',
        'is_available' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A shopper can only accept orders / go online once Admin has
     * approved their KYC. Used by EnsureShopperIsVerified middleware.
     */
    public function isApproved(): bool
    {
        return $this->verification_status === self::STATUS_APPROVED;
    }
}
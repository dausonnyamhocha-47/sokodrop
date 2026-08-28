<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shop model.
 *
 * A Kariakoo merchant's stall. 1:1 with User (role = merchant), hasMany
 * Products. `is_approved` gates visibility in the VIP catalog; `is_active`
 * lets a merchant temporarily pause listings without losing that approval.
 */
class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'shop_name',
        'stall_number',
        'market_section',
        'description',
        'logo_path',
        'business_license_number',
        'is_approved',
        'is_active',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope: only shops that should appear in the public VIP catalog.
     */
    public function scopeVisible($query)
    {
        return $query->where('is_approved', true)->where('is_active', true);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User model.
 *
 * Backs all four roles (vip, merchant, shopper, admin). Role-specific data
 * lives in HasOne relations (ShopperProfile, Shop) rather than on this
 * model directly, keeping it lean and letting each role's profile evolve
 * independently.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Role constants — used throughout controllers/middleware (e.g. CheckRole)
     * instead of magic strings.
     */
    public const ROLE_VIP = 'vip';
    public const ROLE_MERCHANT = 'merchant';
    public const ROLE_SHOPPER = 'shopper';
    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'nida_number',
        'is_verified',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'password' => 'hashed', // Laravel 11 auto-hashes on set, no need for a mutator
    ];

    /**
     * A shopper's extended KYC/operational profile. Only meaningful when
     * role === self::ROLE_SHOPPER, but the relation itself is harmless
     * (returns null) for other roles.
     */
    public function shopperProfile(): HasOne
    {
        return $this->hasOne(ShopperProfile::class);
    }

    /**
     * A merchant's single Kariakoo stall. Only meaningful when
     * role === self::ROLE_MERCHANT.
     */
    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class, 'merchant_id');
    }

    /**
     * Orders placed by this user as a VIP client.
     */
    public function ordersAsClient(): HasMany
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    /**
     * Orders this user has been assigned to fulfill as a shopper.
     */
    public function ordersAsShopper(): HasMany
    {
        return $this->hasMany(Order::class, 'shopper_id');
    }

    public function isVip(): bool
    {
        return $this->role === self::ROLE_VIP;
    }

    public function isMerchant(): bool
    {
        return $this->role === self::ROLE_MERCHANT;
    }

    public function isShopper(): bool
    {
        return $this->role === self::ROLE_SHOPPER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
<?php

namespace App\Http\Middleware;

use App\Models\ShopperProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureShopperIsVerified middleware.
 *
 * Guards shopper-only endpoints (accepting orders, going "available", etc.)
 * so that a shopper whose KYC has not yet been approved by Admin cannot
 * interact with live orders or VIP client data. Applied on top of
 * CheckRole:shopper — this middleware assumes the role check already passed.
 */
class EnsureShopperIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $profile = $user?->shopperProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profaili ya shopper haijakamilika. (Shopper profile not found — complete registration first.)',
            ], 403);
        }

        if ($profile->verification_status !== ShopperProfile::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Akaunti yako ya shopper bado inasubiri uthibitisho wa Admin. (Your shopper account is still pending Admin verification.)',
                'verification_status' => $profile->verification_status,
            ], 403);
        }

        return $next($request);
    }
}
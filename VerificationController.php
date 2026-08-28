<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopperProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * VerificationController (Admin)
 *
 * KYC/approval workflow for shoppers (NIDA + ID document review) and
 * merchants (shop/business license review). Every action here is the
 * gate that unlocks live platform participation — shoppers cannot accept
 * orders (see EnsureShopperIsVerified) and shops cannot appear in the VIP
 * catalog (see Shop::scopeVisible) until approved here.
 */
class VerificationController extends Controller
{
    /**
     * List shopper KYC applications, defaulting to pending ones (the
     * Admin\Dashboard.jsx review queue) but filterable by status.
     */
    public function pendingShoppers(Request $request): JsonResponse
    {
        $status = $request->input('status', ShopperProfile::STATUS_PENDING);

        $profiles = ShopperProfile::query()
            ->where('verification_status', $status)
            ->with('user:id,name,email,phone,nida_number,created_at')
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $profiles,
        ]);
    }

    /**
     * Approve or reject a shopper's KYC application.
     */
    public function reviewShopper(Request $request, ShopperProfile $shopperProfile): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'decision' => ['required', 'in:approved,rejected'],
            'rejection_reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $decision = $request->input('decision');

        $shopperProfile->update([
            'verification_status' => $decision,
            'rejection_reason' => $decision === 'rejected' ? $request->input('rejection_reason') : null,
        ]);

        // Mirror the outcome onto the general user.is_verified flag so other
        // parts of the app (e.g. a simple "verified" badge) don't need to
        // join through shopper_profiles just to check approval status.
        if ($decision === 'approved') {
            $shopperProfile->user()->update(['is_verified' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Uamuzi wa uthibitisho umehifadhiwa.',
            'data' => $shopperProfile->fresh('user'),
        ]);
    }

    /**
     * List shop approval applications, defaulting to pending (not yet approved).
     */
    public function pendingShops(Request $request): JsonResponse
    {
        $approved = $request->boolean('approved', false);

        $shops = Shop::query()
            ->where('is_approved', $approved)
            ->with('merchant:id,name,email,phone,nida_number')
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $shops,
        ]);
    }

    /**
     * Approve or reject a merchant's shop.
     */
    public function reviewShop(Request $request, Shop $shop): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'decision' => ['required', 'in:approved,rejected'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $approved = $request->input('decision') === 'approved';

        $shop->update(['is_approved' => $approved]);

        if ($approved) {
            $shop->merchant()->update(['is_verified' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Uamuzi wa uthibitisho wa duka umehifadhiwa.',
            'data' => $shop->fresh('merchant'),
        ]);
    }

    /**
     * High-level platform stats for the Admin\Dashboard.jsx landing view.
     */
    public function dashboardStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'pending_shopper_verifications' => ShopperProfile::where('verification_status', ShopperProfile::STATUS_PENDING)->count(),
                'pending_shop_approvals' => Shop::where('is_approved', false)->count(),
                'total_approved_shops' => Shop::where('is_approved', true)->count(),
                'total_approved_shoppers' => ShopperProfile::where('verification_status', ShopperProfile::STATUS_APPROVED)->count(),
            ],
        ]);
    }
}
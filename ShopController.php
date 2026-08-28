<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * ShopController (Merchant)
 *
 * Manages a merchant's own stall profile. Every method scopes to
 * $request->user()->shop (or creates it) rather than accepting a shop_id
 * from the client, so a merchant can never read/edit another merchant's shop.
 * New shops start unapproved — they only become visible in the VIP catalog
 * once Admin\VerificationController approves them.
 */
class ShopController extends Controller
{
    /**
     * Get the authenticated merchant's own shop profile.
     */
    public function show(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Bado hujaunda duka. (You have not created a shop yet.)',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $shop,
        ]);
    }

    /**
     * Create the merchant's shop (one per merchant — enforced by the
     * unique constraint on shops.merchant_id at the DB level as a
     * defense-in-depth backstop to this application-level check).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->shop) {
            return response()->json([
                'success' => false,
                'message' => 'Tayari una duka. Tumia PUT kubadilisha taarifa. (You already have a shop. Use update instead.)',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'shop_name' => ['required', 'string', 'max:255'],
            'stall_number' => ['required', 'string', 'max:20'],
            'market_section' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo_path' => ['nullable', 'string'],
            'business_license_number' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $shop = Shop::create([
            ...$validator->validated(),
            'merchant_id' => $user->id,
            // Always starts unapproved — Admin must review stall/business docs
            // before it appears to VIP clients.
            'is_approved' => false,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Duka limeundwa. Linasubiri uthibitisho wa Admin. (Shop created — awaiting Admin approval.)',
            'data' => $shop,
        ], 201);
    }

    /**
     * Update the merchant's own shop details. Note: is_approved is
     * deliberately excluded from mass-updatable fields here — only Admin
     * can flip that flag (see Admin\VerificationController), so editing
     * shop details never bypasses re-review by silently keeping approval.
     */
    public function update(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Bado hujaunda duka. (You have not created a shop yet.)',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'shop_name' => ['sometimes', 'string', 'max:255'],
            'stall_number' => ['sometimes', 'string', 'max:20'],
            'market_section' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo_path' => ['nullable', 'string'],
            'business_license_number' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $shop->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Taarifa za duka zimesasishwa.',
            'data' => $shop->fresh(),
        ]);
    }
}
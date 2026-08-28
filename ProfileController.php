<?php

namespace App\Http\Controllers\Api\Shopper;

use App\Http\Controllers\Controller;
use App\Models\ShopperProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * ProfileController (Shopper)
 *
 * Lets an authenticated shopper view and manage their own KYC/operational
 * profile — vehicle type, availability toggle, and read-only stats (rating,
 * completed_deliveries, verification_status). Deliberately does NOT allow
 * the shopper to self-edit verification_status, id_number, or
 * id_document_path: those fields are Admin-only (see
 * Admin\VerificationController::reviewShopper) so a shopper can never
 * self-approve or tamper with submitted KYC documents after review.
 */
class ProfileController extends Controller
{
    /**
     * View the authenticated shopper's own profile.
     */
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->shopperProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profaili ya shopper haijapatikana. (Shopper profile not found.)',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * Update operational fields only. KYC/verification fields are
     * intentionally excluded from $rules below.
     */
    public function update(Request $request): JsonResponse
    {
        $profile = $request->user()->shopperProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profaili ya shopper haijapatikana. (Shopper profile not found.)',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_type' => ['sometimes', 'in:bicycle,motorcycle,car,on_foot'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $profile->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profaili imesasishwa.',
            'data' => $profile->fresh(),
        ]);
    }

    /**
     * Toggle whether the shopper is currently "online"/available to accept
     * new orders. Kept as its own lightweight endpoint (rather than folded
     * into update()) because the mobile app flips this frequently — e.g. a
     * single tap of an "I'm available" switch — and shouldn't require
     * sending the full profile payload each time.
     *
     * Guards against a shopper going 'available' before Admin approval —
     * this is a second line of defense on top of EnsureShopperIsVerified,
     * which already blocks this route for unapproved shoppers, but keeping
     * the check here too means the rule survives even if a future route
     * change accidentally drops the middleware.
     */
    public function toggleAvailability(Request $request): JsonResponse
    {
        $profile = $request->user()->shopperProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profaili ya shopper haijapatikana. (Shopper profile not found.)',
            ], 404);
        }

        if (! $profile->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Huwezi kuwa online kabla ya uthibitisho wa Admin. (You cannot go online before Admin verification.)',
            ], 403);
        }

        $profile->update(['is_available' => ! $profile->is_available]);

        return response()->json([
            'success' => true,
            'message' => $profile->is_available
                ? 'Sasa uko online na unaweza kupokea oda. (You are now online and can receive orders.)'
                : 'Sasa uko offline. (You are now offline.)',
            'data' => $profile->fresh(),
        ]);
    }
}
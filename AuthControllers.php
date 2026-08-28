<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterShopperRequest;
use App\Models\ShopperProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * AuthController
 *
 * Handles registration (VIP + Shopper) and Sanctum token issuance/revocation
 * for all four roles. Merchant and Admin accounts are provisioned by an
 * existing Admin (see Admin\VerificationController) rather than self-registered,
 * since both require offline vetting before they can transact.
 */
class AuthController extends Controller
{
    /**
     * Public self-registration for VIP clients. Lightweight validation only —
     * VIP discretion means we deliberately don't demand heavy KYC up front,
     * though nida_number can be added later to unlock higher order limits.
     */
    public function registerVip(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone', 'regex:/^\+?[0-9]{9,15}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'], // 'hashed' cast on the model handles hashing
            'role' => User::ROLE_VIP,
        ]);

        $token = $user->createToken('sokodrop-vip')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Usajili umefanikiwa.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Registers a delivery agent (shopper) along with their KYC profile in
     * a single DB transaction. The resulting profile starts as 'pending' —
     * they cannot accept orders until an Admin approves it (see
     * EnsureShopperIsVerified middleware and Admin\VerificationController).
     */
    public function registerShopper(RegisterShopperRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = DB::transaction(function () use ($data) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => $data['password'],
                    'role' => User::ROLE_SHOPPER,
                    'nida_number' => $data['nida_number'],
                ]);

                $profile = ShopperProfile::create([
                    'user_id' => $user->id,
                    'id_number' => $data['id_number'],
                    'id_document_path' => $data['id_document_path'] ?? null,
                    'vehicle_type' => $data['vehicle_type'],
                    'verification_status' => ShopperProfile::STATUS_PENDING,
                ]);

                return [$user, $profile];
            });

            [$user, $profile] = $result;

            $token = $user->createToken('sokodrop-shopper')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Usajili wa shopper umefanikiwa. Subiri uthibitisho wa Admin. (Registration successful — awaiting Admin verification.)',
                'data' => [
                    'user' => $user,
                    'shopper_profile' => $profile,
                    'token' => $token,
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Usajili umeshindikana. Tafadhali jaribu tena. (Registration failed. Please try again.)',
            ], 500);
        }
    }

    /**
     * Universal login for all roles. Returns a Sanctum token scoped by the
     * user's role so the frontend AuthContext can route to the correct
     * dashboard (VIP catalog, Merchant inventory, Shopper deliveries, Admin panel).
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Barua pepe au nenosiri sivyo. (Invalid credentials.)',
            ], 401);
        }

        // Revoke previous tokens on fresh login to avoid unbounded token growth
        // per device — a common footgun in long-lived Sanctum deployments.
        $user->tokens()->delete();

        $token = $user->createToken('sokodrop-' . $user->role)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Umeingia kwa mafanikio.',
            'data' => [
                'user' => $user->load(['shopperProfile', 'shop']),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Umetoka kwa mafanikio.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load(['shopperProfile', 'shop']),
        ]);
    }
}
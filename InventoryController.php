<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * InventoryController (Merchant)
 *
 * CRUD for a merchant's own products. Every query is scoped through
 * $request->user()->shop->products() rather than Product::find(), which
 * means a merchant physically cannot read, edit, or delete another
 * merchant's inventory — ownership is enforced at the query level, not
 * just by an after-the-fact ownership check.
 */
class InventoryController extends Controller
{
    /**
     * List the authenticated merchant's own products.
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Unahitaji kuunda duka kabla ya kuongeza bidhaa. (You must create a shop before adding products.)',
            ], 404);
        }

        $products = $shop->products()->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Unahitaji kuunda duka kabla ya kuongeza bidhaa. (You must create a shop before adding products.)',
            ], 404);
        }

        if (! $shop->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Duka lako bado halijathibitishwa na Admin. (Your shop is not yet approved by Admin.)',
            ], 403);
        }

        // Field-shape validation now lives in StoreProductRequest; this
        // controller only enforces the business rules above (shop exists +
        // is approved), which aren't cleanly expressible as FormRequest rules
        // since they depend on the authenticated user's own shop record.
        $product = $shop->products()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bidhaa imeongezwa.',
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        if ($product->shop_id !== $request->user()->shop?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Huna ruhusa ya kubadilisha bidhaa hii. (You do not have permission to edit this product.)',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'image_path' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Note: editing price/name here only affects future orders — past
        // OrderItem rows keep their own unit_price/product_name_snapshot,
        // so historical totals and commission records are never disturbed.
        $product->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Bidhaa imesasishwa.',
            'data' => $product->fresh(),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        if ($product->shop_id !== $request->user()->shop?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Huna ruhusa ya kufuta bidhaa hii. (You do not have permission to delete this product.)',
            ], 403);
        }

        // Soft-remove via is_active rather than a hard delete: past OrderItem
        // rows reference product_id with onDelete('restrict'), so a hard
        // delete would fail anyway once the product has been ordered.
        $product->update(['is_active' => false, 'stock_quantity' => 0]);

        return response()->json([
            'success' => true,
            'message' => 'Bidhaa imeondolewa kwenye orodha. (Product removed from listing.)',
        ]);
    }
}
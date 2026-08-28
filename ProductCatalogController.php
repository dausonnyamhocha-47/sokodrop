<?php

namespace App\Http\Controllers\Api\VIP;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * ProductCatalogController (VIP)
 *
 * Read-only browsing endpoints for VIP clients. Only ever exposes products
 * belonging to approved + active shops (Shop::scopeVisible) and active
 * products with stock (Product::scopeAvailable) — merchants never appear
 * in the catalog until Admin approval, and out-of-stock items are hidden
 * rather than shown as disabled, to keep the VIP browsing experience clean.
 */
class ProductCatalogController extends Controller
{
    /**
     * List/browse products, with optional search, category filter, and
     * shop filter. Paginated to keep payloads small on mobile.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->available()
            ->whereHas('shop', fn ($q) => $q->visible())
            ->with(['shop:id,shop_name,stall_number,market_section']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->whereFullText(['name', 'description'], $search);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->integer('shop_id'));
        }

        $perPage = min($request->integer('per_page', 20), 100);

        $products = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Single product detail view, including its parent shop's public info.
     */
    public function show(Product $product): JsonResponse
    {
        if (! $product->is_active || ! $product->shop || ! $product->shop->is_approved || ! $product->shop->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Bidhaa haipatikani. (Product not available.)',
            ], 404);
        }

        $product->load(['shop:id,shop_name,stall_number,market_section,description']);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Distinct list of active categories across visible shops, used to
     * populate the Catalog.jsx filter sidebar.
     */
    public function categories(): JsonResponse
    {
        $categories = Product::query()
            ->available()
            ->whereHas('shop', fn ($q) => $q->visible())
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Approved + active shops, for a "browse by merchant" view.
     */
    public function shops(): JsonResponse
    {
        $shops = Shop::query()
            ->visible()
            ->withCount('products')
            ->orderBy('shop_name')
            ->get(['id', 'shop_name', 'stall_number', 'market_section', 'description', 'logo_path']);

        return response()->json([
            'success' => true,
            'data' => $shops,
        ]);
    }
}
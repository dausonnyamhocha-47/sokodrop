<?php

use App\Http\Controllers\Api\Admin\RevenueController;
use App\Http\Controllers\Api\Admin\VerificationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Merchant\InventoryController;
use App\Http\Controllers\Api\Merchant\ShopController;
use App\Http\Controllers\Api\Shopper\DeliveryController;
use App\Http\Controllers\Api\Shopper\ProfileController;
use App\Http\Controllers\Api\VIP\OrderController;
use App\Http\Controllers\Api\VIP\ProductCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sokodrop API Routes
|--------------------------------------------------------------------------
|
| All routes are stateless JSON endpoints authenticated via Laravel Sanctum
| bearer tokens (auth:sanctum). Role isolation is enforced by the 'role'
| middleware alias (App\Http\Middleware\CheckRole), and shopper-specific
| routes are additionally gated by 'shopper.verified'
| (App\Http\Middleware\EnsureShopperIsVerified). Register both aliases in
| bootstrap/app.php:
|
|   $middleware->alias([
|       'role' => \App\Http\Middleware\CheckRole::class,
|       'shopper.verified' => \App\Http\Middleware\EnsureShopperIsVerified::class,
|   ]);
|
*/

// ---------------------------------------------------------------------
// Public auth routes — no token required.
// ---------------------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('register/vip', [AuthController::class, 'registerVip']);
    Route::post('register/shopper', [AuthController::class, 'registerShopper']);
    Route::post('login', [AuthController::class, 'login']);
});

// ---------------------------------------------------------------------
// Public catalog browsing — VIP discretion still requires login to place
// orders, but the catalog itself is browsable to encourage sign-ups.
// ---------------------------------------------------------------------
Route::prefix('catalog')->group(function () {
    Route::get('products', [ProductCatalogController::class, 'index']);
    Route::get('products/{product}', [ProductCatalogController::class, 'show']);
    Route::get('categories', [ProductCatalogController::class, 'categories']);
    Route::get('shops', [ProductCatalogController::class, 'shops']);
});

// ---------------------------------------------------------------------
// Authenticated routes — require a valid Sanctum token.
// ---------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {

    // Shared account routes, any role.
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // -------------------------------------------------------------
    // VIP client routes.
    // -------------------------------------------------------------
    Route::prefix('vip')->middleware('role:vip')->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    });

    // -------------------------------------------------------------
    // Merchant routes.
    // -------------------------------------------------------------
    Route::prefix('merchant')->middleware('role:merchant')->group(function () {
        Route::get('shop', [ShopController::class, 'show']);
        Route::post('shop', [ShopController::class, 'store']);
        Route::put('shop', [ShopController::class, 'update']);

        Route::get('products', [InventoryController::class, 'index']);
        Route::post('products', [InventoryController::class, 'store']);
        Route::put('products/{product}', [InventoryController::class, 'update']);
        Route::delete('products/{product}', [InventoryController::class, 'destroy']);
    });

    // -------------------------------------------------------------
    // Shopper (delivery agent) routes. Note the extra 'shopper.verified'
    // gate on top of the role check — an unapproved shopper can be
    // authenticated but still cannot touch live order data.
    // -------------------------------------------------------------
    Route::prefix('shopper')->middleware('role:shopper')->group(function () {
        // Profile routes are reachable even before approval so a shopper can
        // at least view their pending status — toggleAvailability() itself
        // still enforces isApproved() internally as defense-in-depth.
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::post('profile/toggle-availability', [ProfileController::class, 'toggleAvailability']);

        Route::middleware('shopper.verified')->group(function () {
            Route::get('orders/available', [DeliveryController::class, 'available']);
            Route::get('orders/mine', [DeliveryController::class, 'myDeliveries']);
            Route::post('orders/{order}/accept', [DeliveryController::class, 'accept']);
            Route::post('orders/{order}/start-shopping', [DeliveryController::class, 'startShopping']);
            Route::post('orders/{order}/items', [DeliveryController::class, 'updateItem']);
            Route::post('orders/{order}/start-delivering', [DeliveryController::class, 'startDelivering']);
            Route::post('orders/{order}/complete', [DeliveryController::class, 'complete']);
        });
    });

    // -------------------------------------------------------------
    // Admin routes.
    // -------------------------------------------------------------
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('dashboard/stats', [VerificationController::class, 'dashboardStats']);

        Route::get('shoppers/pending', [VerificationController::class, 'pendingShoppers']);
        Route::post('shoppers/{shopperProfile}/review', [VerificationController::class, 'reviewShopper']);

        Route::get('shops/pending', [VerificationController::class, 'pendingShops']);
        Route::post('shops/{shop}/review', [VerificationController::class, 'reviewShop']);

        // Revenue / financial reporting — read-only, built on paid transactions.
        Route::get('revenue/summary', [RevenueController::class, 'summary']);
        Route::get('revenue/ledger', [RevenueController::class, 'ledger']);
        Route::get('revenue/top-shops', [RevenueController::class, 'topShops']);
    });
});
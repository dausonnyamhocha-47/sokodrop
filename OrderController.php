<?php

namespace App\Http\Controllers\Api\VIP;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CommissionCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * OrderController (VIP)
 *
 * Handles checkout (order creation) and order tracking for VIP clients.
 * All money math delegates to CommissionCalculator (master directive rule #4)
 * — this controller never computes totals itself, only orchestrates the
 * DB transaction: validate stock -> snapshot items -> decrement stock ->
 * calculate totals -> persist.
 */
class OrderController extends Controller
{
    public function __construct(private readonly CommissionCalculator $commissionCalculator)
    {
    }

    /**
     * List the authenticated VIP client's own orders, most recent first.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('client_id', $request->user()->id)
            ->with(['items.product:id,name,image_path', 'transaction'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Create a new order from the VIP client's cart. Wrapped in a DB
     * transaction with row-level locking on products (lockForUpdate) to
     * prevent two simultaneous checkouts from oversubscribing the same
     * limited stock — important for popular Kariakoo items.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $client = $request->user();

        try {
            $order = DB::transaction(function () use ($data, $client) {
                $order = Order::create([
                    'client_id' => $client->id,
                    'status' => Order::STATUS_PENDING,
                    'delivery_address' => $data['delivery_address'],
                    'delivery_notes' => $data['delivery_notes'] ?? null,
                    // subtotal/total are placeholders here; recalculated below
                    // once line items exist, via CommissionCalculator.
                    'subtotal_amount' => 0,
                    'delivery_fee' => 0,
                    'service_fee' => 0,
                    'total_amount' => 0,
                ]);

                foreach ($data['items'] as $item) {
                    // lockForUpdate: hold a row lock until the transaction commits,
                    // so concurrent checkouts can't both pass the stock check
                    // against the same stale quantity.
                    $product = Product::query()
                        ->where('id', $item['product_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $product->is_active) {
                        throw ValidationException::withMessages([
                            'items' => "Bidhaa '{$product->name}' haipatikani tena. (Product '{$product->name}' is no longer available.)",
                        ]);
                    }

                    if (! $product->hasSufficientStock($item['quantity'])) {
                        throw ValidationException::withMessages([
                            'items' => "Stock haitoshi kwa '{$product->name}'. Iliyopo: {$product->stock_quantity}. (Insufficient stock for '{$product->name}'. Available: {$product->stock_quantity}.)",
                        ]);
                    }

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        // Snapshot name/price now — future edits to $product
                        // never retroactively change this historical line item.
                        'product_name_snapshot' => $product->name,
                        'unit_price' => $product->price,
                        'quantity' => $item['quantity'],
                        'subtotal' => round((float) $product->price * $item['quantity'], 2),
                    ]);

                    $product->decrement('stock_quantity', $item['quantity']);
                }

                // Single source of truth for totals: CommissionCalculator sums
                // the just-created line items and applies delivery/service fees.
                $this->commissionCalculator->applyToOrder($order, (float) $data['delivery_fee']);

                return $order->fresh(['items.product', 'client']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Oda imewekwa kwa mafanikio. (Order placed successfully.)',
                'data' => $order,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Imeshindikana kuweka oda. Tafadhali jaribu tena. (Failed to place order. Please try again.)',
            ], 500);
        }
    }

    /**
     * Show a single order — scoped to the requesting client so one VIP
     * client can never view another's order (authorization by ownership,
     * not just authentication).
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        // Delegates to OrderPolicy::view — centralizes the ownership rule
        // (client, assigned shopper, or admin) instead of repeating the
        // raw client_id comparison in every controller that touches Order.
        $this->authorize('view', $order);

        $order->load(['items.product', 'shopper:id,name,phone', 'transaction']);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Cancel an order — only allowed while it's still pending/accepted
     * (see Order::isCancellable), i.e. before the shopper has started
     * physically shopping at Kariakoo.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        // OrderPolicy::cancel already checks both ownership AND
        // isCancellable(), but we keep a distinct 422 message below for the
        // "wrong stage" case so the frontend can tell the two failure modes
        // apart (403 Forbidden vs 422 wrong-status is more useful to Checkout.jsx).
        if ($order->client_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Huna ruhusa ya kufuta oda hii. (You do not have permission to cancel this order.)',
            ], 403);
        }

        if (! $order->isCancellable()) {
            return response()->json([
                'success' => false,
                'message' => 'Oda hii haiwezi kufutwa katika hatua hii. (This order can no longer be cancelled at its current stage.)',
            ], 422);
        }

        try {
            DB::transaction(function () use ($order, $request) {
                // Restock every item since the shopper never left Kariakoo with them.
                foreach ($order->items as $item) {
                    Product::where('id', $item->product_id)->increment('stock_quantity', $item->quantity);
                }

                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $request->input('reason', 'Imefutwa na mteja.'),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Oda imefutwa. (Order cancelled.)',
                'data' => $order->fresh(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Imeshindikana kufuta oda. (Failed to cancel order.)',
            ], 500);
        }
    }
}
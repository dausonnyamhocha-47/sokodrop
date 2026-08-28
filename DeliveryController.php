<?php

namespace App\Http\Controllers\Api\Shopper;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * DeliveryController (Shopper)
 *
 * Drives the shopper-side fulfillment lifecycle:
 * pending -> accepted -> shopping -> delivering -> completed
 *
 * Every mutating action is scoped to either an unassigned order (for
 * accept()) or an order already assigned to $request->user() (for every
 * later transition), so a shopper can never manipulate another shopper's
 * delivery. Reachable only via routes gated by EnsureShopperIsVerified.
 */
class DeliveryController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * Orders available to be picked up — pending and unassigned. Shoppers
     * see anonymized delivery info (delivery_code, address, item list) but
     * never the VIP client's name/contact directly, preserving discretion
     * until they've formally accepted the order.
     */
    public function available(): JsonResponse
    {
        $orders = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->whereNull('shopper_id')
            ->with(['items.product:id,name,image_path'])
            ->orderBy('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Orders currently assigned to the authenticated shopper (in progress
     * or recently completed), for the Deliveries.jsx dashboard.
     */
    public function myDeliveries(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('shopper_id', $request->user()->id)
            ->with(['items.product', 'transaction'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Accept a pending order. Uses a row lock + status re-check inside the
     * transaction to prevent two shoppers from both accepting the same
     * order in a race condition.
     */
    public function accept(Request $request, Order $order): JsonResponse
    {
        try {
            $order = DB::transaction(function () use ($order, $request) {
                $locked = Order::query()->where('id', $order->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== Order::STATUS_PENDING || $locked->shopper_id !== null) {
                    abort(409, 'Oda hii tayari imechukuliwa na shopper mwingine. (This order has already been accepted by another shopper.)');
                }

                $locked->update([
                    'status' => Order::STATUS_ACCEPTED,
                    'shopper_id' => $request->user()->id,
                    'accepted_at' => now(),
                ]);

                return $locked;
            });

            return response()->json([
                'success' => true,
                'message' => 'Umeikubali oda. (Order accepted.)',
                'data' => $order->fresh(['items.product']),
            ]);
        } catch (Throwable $e) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Imeshindikana kukubali oda.',
            ], $status ?: 500);
        }
    }

    /**
     * Mark that the shopper has begun physically shopping at Kariakoo.
     */
    public function startShopping(Request $request, Order $order): JsonResponse
    {
        return $this->transitionStatus($request, $order, Order::STATUS_ACCEPTED, Order::STATUS_SHOPPING);
    }

    /**
     * Mark an individual line item as quality-checked (or substituted) while
     * the shopper is physically inspecting goods at the stall.
     */
    public function updateItem(Request $request, Order $order): JsonResponse
    {
        if ($order->shopper_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Huna ruhusa ya oda hii. (You do not have permission for this order.)',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'quality_checked' => ['sometimes', 'boolean'],
            'is_substituted' => ['sometimes', 'boolean'],
            'substitution_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $item = $order->items()->where('id', $request->integer('order_item_id'))->firstOrFail();
        $item->update($validator->safe()->except('order_item_id'));

        return response()->json([
            'success' => true,
            'message' => 'Bidhaa imesasishwa.',
            'data' => $item->fresh(),
        ]);
    }

    /**
     * Mark that the shopper has left Kariakoo and is en route to the VIP client.
     */
    public function startDelivering(Request $request, Order $order): JsonResponse
    {
        return $this->transitionStatus($request, $order, Order::STATUS_SHOPPING, Order::STATUS_DELIVERING);
    }

    /**
     * Complete the delivery. This is the point at which payment collection
     * is initiated (via PaymentService, which computes the merchant/shopper/
     * platform payout split through CommissionCalculator) — deliberately
     * deferred until delivery is confirmed rather than at order creation,
     * since cancellations before this point should never generate a payout
     * obligation.
     */
    public function complete(Request $request, Order $order): JsonResponse
    {
        if (! $request->user()->can('fulfill', $order)) {
            return response()->json([
                'success' => false,
                'message' => 'Huna ruhusa ya oda hii. (You do not have permission for this order.)',
            ], 403);
        }

        if ($order->status !== Order::STATUS_DELIVERING) {
            return response()->json([
                'success' => false,
                'message' => 'Oda lazima iwe katika hatua ya kusafirisha kabla ya kukamilika. (Order must be in delivering status before it can be completed.)',
            ], 422);
        }

        try {
            $order = DB::transaction(function () use ($order, $request) {
                $order->update([
                    'status' => Order::STATUS_COMPLETED,
                    'delivered_at' => now(),
                ]);

                // PaymentService owns the payment lifecycle; it internally
                // calls CommissionCalculator for the payout split so this
                // controller never touches money math directly.
                $this->paymentService->initiate(
                    $order,
                    $request->input('payment_method', 'mobile_money'),
                    $request->input('payment_gateway'),
                );

                // Bump the shopper's completed_deliveries counter for their
                // public rating/reliability stats.
                $request->user()->shopperProfile?->increment('completed_deliveries');

                return $order;
            });

            return response()->json([
                'success' => true,
                'message' => 'Oda imekamilika. (Order completed.)',
                'data' => $order->fresh(['items', 'transaction']),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Imeshindikana kukamilisha oda. (Failed to complete order.)',
            ], 500);
        }
    }

    /**
     * Shared guard for simple linear status transitions, ensuring
     * ownership + the correct "from" status before moving to "to".
     */
    private function transitionStatus(Request $request, Order $order, string $from, string $to): JsonResponse
    {
        // OrderPolicy::fulfill centralizes the "is this MY assigned order"
        // check shared by every fulfillment transition in this controller.
        if (! $request->user()->can('fulfill', $order)) {
            return response()->json([
                'success' => false,
                'message' => 'Huna ruhusa ya oda hii. (You do not have permission for this order.)',
            ], 403);
        }

        if ($order->status !== $from) {
            return response()->json([
                'success' => false,
                'message' => "Oda lazima iwe katika hatua ya '{$from}' kabla ya kuendelea. (Order must be in '{$from}' status before this transition.)",
            ], 422);
        }

        $order->update(['status' => $to]);

        return response()->json([
            'success' => true,
            'message' => 'Hali ya oda imesasishwa.',
            'data' => $order->fresh(),
        ]);
    }
}
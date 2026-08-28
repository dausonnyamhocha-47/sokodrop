<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * RevenueController (Admin)
 *
 * Read-only platform financial reporting, built entirely on `transactions`
 * rows with payment_status = 'paid' (unpaid/failed transactions represent
 * no realized revenue yet, and refunded ones are explicitly excluded from
 * totals but surfaced separately so refund volume stays visible to Admins).
 * This controller never mutates Transaction — writes to it belong to
 * PaymentService and Shopper\DeliveryController.
 */
class RevenueController extends Controller
{
    /**
     * High-level platform revenue summary for an optional date range,
     * defaulting to all-time if no range is given. Powers the financial
     * section of Admin\Dashboard.jsx.
     */
    public function summary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Uthibitishaji umeshindikana.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Transaction::query()->where('payment_status', Transaction::STATUS_PAID);
        $this->applyDateRange($query, $request);

        $totals = (clone $query)->selectRaw('
                COALESCE(SUM(merchant_payout), 0) as total_merchant_payouts,
                COALESCE(SUM(shopper_payout), 0) as total_shopper_payouts,
                COALESCE(SUM(platform_commission), 0) as total_platform_commission,
                COALESCE(SUM(merchant_payout + shopper_payout + platform_commission), 0) as total_processed,
                COUNT(*) as paid_transaction_count
            ')->first();

        $refundedCount = Transaction::where('payment_status', Transaction::STATUS_REFUNDED)
            ->when($request->filled('from'), fn ($q) => $q->whereDate('refunded_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('refunded_at', '<=', $request->date('to')))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $request->input('from'),
                    'to' => $request->input('to'),
                ],
                'total_platform_commission' => (float) $totals->total_platform_commission,
                'total_merchant_payouts' => (float) $totals->total_merchant_payouts,
                'total_shopper_payouts' => (float) $totals->total_shopper_payouts,
                'total_processed' => (float) $totals->total_processed,
                'paid_transaction_count' => (int) $totals->paid_transaction_count,
                'refunded_transaction_count' => $refundedCount,
            ],
        ]);
    }

    /**
     * Paginated raw ledger of paid transactions, for Admin drill-down /
     * export. Kept separate from summary() so the aggregate query above
     * never has to paginate large row sets.
     */
    public function ledger(Request $request): JsonResponse
    {
        $query = Transaction::query()
            ->where('payment_status', Transaction::STATUS_PAID)
            ->with(['order:id,client_id,total_amount,delivered_at', 'order.client:id,name']);

        $this->applyDateRange($query, $request);

        $transactions = $query->orderByDesc('paid_at')->paginate(30);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Top-earning shops by merchant_payout, joined through orders/order_items
     * back to their shop. Useful for Admin to spot the platform's highest-
     * volume Kariakoo stalls.
     */
    public function topShops(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 10), 50);

        $topShops = Transaction::query()
            ->where('transactions.payment_status', Transaction::STATUS_PAID)
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->groupBy('shops.id', 'shops.shop_name')
            ->selectRaw('shops.id as shop_id, shops.shop_name, SUM(order_items.subtotal) as total_sales')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topShops,
        ]);
    }

    /**
     * Shared date-range filter applied to both summary() and ledger(),
     * scoped on paid_at (when revenue was actually realized) rather than
     * created_at (when the transaction row was first opened).
     */
    private function applyDateRange($query, Request $request): void
    {
        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', $request->date('to'));
        }
    }
}
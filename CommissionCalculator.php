<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use InvalidArgumentException;

/**
 * CommissionCalculator
 *
 * Single source of truth for all Sokodrop money math (master directive
 * rule #4). Every calculation here uses (int) cents internally to avoid
 * floating point drift, then converts back to a 2-decimal string/float
 * only at the boundary (when persisting to the decimal(10,2) columns).
 *
 * Business rule: subtotal_amount + delivery_fee + service_fee = total_amount
 * and merchant_payout + shopper_payout + platform_commission = total_amount.
 */
class CommissionCalculator
{
    /**
     * Percentage of subtotal_amount retained by the platform as commission
     * from the merchant's side (e.g. 8%). Configurable via config/sokodrop.php
     * in a real deployment; hardcoded as a documented constant here for clarity.
     */
    private const PLATFORM_COMMISSION_RATE = 0.08;

    /**
     * Percentage of the delivery_fee the platform keeps before paying the
     * shopper (e.g. 15%), representing platform overhead (insurance,
     * dispute handling, support).
     */
    private const PLATFORM_DELIVERY_CUT_RATE = 0.15;

    /**
     * Flat service fee charged to the VIP client per order, covering the
     * discretion/concierge premium. Kept as a flat amount rather than a
     * percentage so pricing stays predictable regardless of basket size.
     */
    private const FLAT_SERVICE_FEE = 3000.00; // TZS

    /**
     * Compute the full order-level totals from a raw subtotal.
     *
     * @return array{subtotal_amount: float, delivery_fee: float, service_fee: float, total_amount: float}
     */
    public function calculateOrderTotals(float $subtotalAmount, float $deliveryFee): array
    {
        if ($subtotalAmount < 0 || $deliveryFee < 0) {
            throw new InvalidArgumentException('Amounts cannot be negative.');
        }

        $subtotalCents = $this->toCents($subtotalAmount);
        $deliveryCents = $this->toCents($deliveryFee);
        $serviceCents = $this->toCents(self::FLAT_SERVICE_FEE);

        $totalCents = $subtotalCents + $deliveryCents + $serviceCents;

        return [
            'subtotal_amount' => $this->toDecimal($subtotalCents),
            'delivery_fee' => $this->toDecimal($deliveryCents),
            'service_fee' => $this->toDecimal($serviceCents),
            'total_amount' => $this->toDecimal($totalCents),
        ];
    }

    /**
     * Split an order's total into merchant, shopper, and platform payouts.
     * Uses integer-cent arithmetic throughout and reconciles any rounding
     * remainder into the platform_commission bucket so the three payouts
     * always sum EXACTLY to total_amount (no silent 1-cent drift).
     *
     * @return array{merchant_payout: float, shopper_payout: float, platform_commission: float}
     */
    public function splitPayout(Order $order): array
    {
        $subtotalCents = $this->toCents((float) $order->subtotal_amount);
        $deliveryCents = $this->toCents((float) $order->delivery_fee);
        $serviceCents = $this->toCents((float) $order->service_fee);
        $totalCents = $subtotalCents + $deliveryCents + $serviceCents;

        // Merchant receives subtotal minus platform's product commission.
        $merchantCommissionCents = (int) round($subtotalCents * self::PLATFORM_COMMISSION_RATE);
        $merchantPayoutCents = $subtotalCents - $merchantCommissionCents;

        // Shopper receives delivery_fee minus platform's delivery cut.
        $deliveryCutCents = (int) round($deliveryCents * self::PLATFORM_DELIVERY_CUT_RATE);
        $shopperPayoutCents = $deliveryCents - $deliveryCutCents;

        // Platform keeps: product commission + delivery cut + the full flat service fee.
        $platformCents = $merchantCommissionCents + $deliveryCutCents + $serviceCents;

        // Reconciliation guard: floating/rounding operations above must never
        // cause the three payouts to miss total_amount. If they do (which
        // should not happen given integer math, but we guard defensively
        // against future rate changes), push the remainder into platform_commission.
        $sumCents = $merchantPayoutCents + $shopperPayoutCents + $platformCents;
        $remainder = $totalCents - $sumCents;
        if ($remainder !== 0) {
            $platformCents += $remainder;
        }

        return [
            'merchant_payout' => $this->toDecimal($merchantPayoutCents),
            'shopper_payout' => $this->toDecimal($shopperPayoutCents),
            'platform_commission' => $this->toDecimal($platformCents),
        ];
    }

    /**
     * Recalculate an order's totals from its current line items and persist
     * them. Does not touch status or assignment fields.
     */
    public function applyToOrder(Order $order, float $deliveryFee): Order
    {
        $subtotal = (float) $order->items()->sum('subtotal');
        $totals = $this->calculateOrderTotals($subtotal, $deliveryFee);

        $order->fill($totals);
        $order->save();

        return $order;
    }

    /**
     * Create (or update) the Transaction row for an order with the computed
     * three-way payout split. Called once payment_status transitions to 'paid'.
     */
    public function recordTransactionPayout(Order $order, Transaction $transaction): Transaction
    {
        $payout = $this->splitPayout($order);

        $transaction->fill($payout);
        $transaction->save();

        return $transaction;
    }

    /**
     * Convert a decimal TZS amount to integer cents to avoid float errors.
     * Sokodrop stores TZS with 2 implied decimal places even though the
     * currency has no minor unit in everyday use, to stay consistent with
     * the decimal(10,2) schema and allow future multi-currency support.
     */
    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function toDecimal(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
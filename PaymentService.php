<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PaymentService
 *
 * Owns the *payment lifecycle* (initiate -> gateway confirms -> paid, or
 * -> failed / refunded) as a distinct concern from CommissionCalculator,
 * which only owns the *money math* (how much each party is owed). This
 * service calls into CommissionCalculator to get those figures but never
 * recomputes them itself — one source of truth for arithmetic, one source
 * of truth for payment state transitions.
 *
 * NOTE: Actual mobile money / card gateway HTTP integration (M-Pesa, Tigo
 * Pesa, Airtel Money, etc.) is deliberately stubbed behind
 * dispatchToGateway() — a real deployment would inject a gateway client
 * here. Keeping that boundary explicit means swapping providers, or
 * supporting several at once, never touches the rest of the order/
 * transaction domain logic.
 */
class PaymentService
{
    public function __construct(private readonly CommissionCalculator $commissionCalculator)
    {
    }

    /**
     * Begin collecting payment for a completed order. Creates the
     * Transaction row in 'pending' state and (in a real deployment) would
     * push a payment request to the mobile money gateway (e.g. an STK-style
     * prompt to the VIP client's phone).
     */
    public function initiate(Order $order, string $paymentMethod, ?string $paymentGateway = null): Transaction
    {
        $transaction = Transaction::firstOrNew(['order_id' => $order->id]);

        $transaction->payment_method = $paymentMethod;
        $transaction->payment_gateway = $paymentGateway;
        $transaction->payment_status = Transaction::STATUS_PENDING;

        // Pre-fill the payout split now so support/admin screens can see the
        // expected breakdown even before the gateway confirms payment.
        $payout = $this->commissionCalculator->splitPayout($order);
        $transaction->fill($payout);
        $transaction->save();

        $this->dispatchToGateway($order, $transaction);

        return $transaction;
    }

    /**
     * Confirm a successful payment — called from a gateway webhook handler
     * (not included here; that's a routes/HTTP concern) once the mobile
     * money provider confirms funds were received.
     */
    public function confirmPaid(Transaction $transaction, ?string $externalReference = null): Transaction
    {
        if ($transaction->payment_status === Transaction::STATUS_PAID) {
            return $transaction; // Idempotent — webhook retries should never double-process.
        }

        $transaction->payment_status = Transaction::STATUS_PAID;
        $transaction->paid_at = now();

        if ($externalReference) {
            // Preserve our own SKD- reference but log the gateway's external
            // one for reconciliation, rather than overwriting transaction_reference.
            Log::info('Sokodrop payment confirmed', [
                'order_id' => $transaction->order_id,
                'internal_reference' => $transaction->transaction_reference,
                'gateway_reference' => $externalReference,
            ]);
        }

        $transaction->save();

        return $transaction;
    }

    /**
     * Mark a payment attempt as failed (e.g. insufficient funds, gateway
     * timeout, client cancelled the mobile money prompt). Does NOT cancel
     * the underlying order — that's a separate decision left to the caller,
     * since a failed payment on a completed delivery may just need a retry.
     */
    public function markFailed(Transaction $transaction, ?string $reason = null): Transaction
    {
        $transaction->payment_status = Transaction::STATUS_FAILED;
        $transaction->save();

        Log::warning('Sokodrop payment failed', [
            'order_id' => $transaction->order_id,
            'reference' => $transaction->transaction_reference,
            'reason' => $reason,
        ]);

        return $transaction;
    }

    /**
     * Refund a paid transaction. Only allowed from the 'paid' state —
     * refunding a pending/failed transaction makes no sense since no
     * funds were ever collected.
     */
    public function refund(Transaction $transaction, ?string $reason = null): Transaction
    {
        if ($transaction->payment_status !== Transaction::STATUS_PAID) {
            throw new RuntimeException('Malipo ya awali hayajakamilika, hivyo hayawezi kurejeshwa. (Only a paid transaction can be refunded.)');
        }

        $transaction->payment_status = Transaction::STATUS_REFUNDED;
        $transaction->refunded_at = now();
        $transaction->save();

        Log::info('Sokodrop payment refunded', [
            'order_id' => $transaction->order_id,
            'reference' => $transaction->transaction_reference,
            'reason' => $reason,
        ]);

        return $transaction;
    }

    /**
     * Stub for the outbound call to a real mobile-money/card gateway.
     * Swap this for an actual HTTP client (M-Pesa Daraja API, Tigo Pesa,
     * Airtel Money Open API, etc.) in production. Left as a logged no-op
     * here so the rest of the payment lifecycle can be built, tested, and
     * demoed before gateway credentials exist.
     */
    private function dispatchToGateway(Order $order, Transaction $transaction): void
    {
        Log::info('Sokodrop payment dispatched to gateway (stub)', [
            'order_id' => $order->id,
            'transaction_reference' => $transaction->transaction_reference,
            'amount' => $order->total_amount,
            'method' => $transaction->payment_method,
        ]);
    }
}
<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * OrderPolicy
 *
 * Centralizes the ownership checks that were previously duplicated inline
 * across VIP\OrderController and Shopper\DeliveryController (e.g.
 * `if ($order->client_id !== $request->user()->id)`). Laravel auto-discovers
 * this policy for the Order model by naming convention (Order -> OrderPolicy),
 * so it can be used via $this->authorize('view', $order) or
 * Gate::allows('view', $order) without any manual registration.
 */
class OrderPolicy
{
    /**
     * VIP client viewing their own order, or the shopper it's assigned to,
     * or an admin (any admin can view any order for support/dispute purposes).
     */
    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin()
            || $order->client_id === $user->id
            || $order->shopper_id === $user->id;
    }

    /**
     * Only the VIP client who placed the order may cancel it, and only
     * while it's still in a cancellable status (delegated to the model
     * so the business rule lives in one place: Order::isCancellable()).
     */
    public function cancel(User $user, Order $order): bool
    {
        return $order->client_id === $user->id && $order->isCancellable();
    }

    /**
     * Only the shopper currently assigned to this order may accept/progress/
     * complete it — used by every fulfillment transition in DeliveryController.
     */
    public function fulfill(User $user, Order $order): bool
    {
        return $user->isShopper() && $order->shopper_id === $user->id;
    }

    /**
     * An unassigned, still-pending order can be accepted by any verified
     * shopper (verification itself is enforced separately by the
     * EnsureShopperIsVerified middleware, not here).
     */
    public function accept(User $user, Order $order): bool
    {
        return $user->isShopper()
            && $order->shopper_id === null
            && $order->status === Order::STATUS_PENDING;
    }
}
<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\StockHold;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every change to a variant's stock goes through here, apart from an admin
 * recount (which still writes its movement through `recordAdjustment`).
 *
 * Holds are one `stock_holds` row per order per variant, each with its own
 * expiry, so a 15-minute web checkout and a WhatsApp order waiting a day on
 * the same size run out independently. `quantity_reserved` is the sum of the
 * active holds, recounted under the item's row lock after every change, and
 * each change appends an `inventory_movements` row in the same transaction.
 *
 * Lock order is always the inventory item first, then its holds, so two
 * callers can never wait on each other in opposite directions.
 */
class InventoryReservationService
{
    /**
     * Soft-reserve `$quantity` units for `$order` (Feature 3). Locks the row
     * (SELECT ... FOR UPDATE) so two concurrent requests for the last unit
     * serialize instead of both reading the same sellable count and both
     * succeeding — the correctness guarantee holds regardless of how stale the
     * storefront's polled stock display is.
     *
     * @throws InsufficientStockException
     */
    public function reserve(InventoryItem $item, int $quantity, CarbonInterface $expiresAt, Order $order): InventoryItem
    {
        return DB::transaction(function () use ($item, $quantity, $expiresAt, $order) {
            $locked = $this->lock($item->id);

            // Lazy sweep between scheduler ticks: a lapsed hold must not block
            // a live customer.
            $this->expireLapsedHolds($locked);

            if ($locked->sellable_quantity < $quantity) {
                throw new InsufficientStockException($locked, $quantity);
            }

            StockHold::create([
                'inventory_item_id' => $locked->id,
                'order_id' => $order->id,
                'quantity' => $quantity,
                'channel' => $order->channel ?? 'web',
                'expires_at' => $expiresAt,
            ]);

            $this->recount($locked);
            $this->record($locked, InventoryMovement::HOLD, reserved: $quantity, order: $order);

            return $locked;
        });
    }

    /**
     * Releases every active hold `$order` has, without a sale: the checkout
     * was abandoned, the payment session never opened, or the order was
     * cancelled. Returns the number of units released.
     */
    public function releaseOrder(Order $order, ?string $note = null): int
    {
        $itemIds = StockHold::query()->active()->where('order_id', $order->id)
            ->orderBy('inventory_item_id')->distinct()->pluck('inventory_item_id');

        $released = 0;

        foreach ($itemIds as $itemId) {
            DB::transaction(function () use ($itemId, $order, $note, &$released) {
                $locked = $this->lock($itemId);

                foreach ($this->activeHolds($locked, $order) as $hold) {
                    $hold->update(['released_at' => now(), 'release_reason' => 'released']);
                    $this->recount($locked);
                    $this->record($locked, InventoryMovement::HOLD_RELEASED, reserved: -$hold->quantity, order: $order, note: $note);
                    $released += $hold->quantity;
                }
            });
        }

        return $released;
    }

    /**
     * Payment confirmed — converts `$order`'s hold on this variant into a
     * real, permanent decrement.
     *
     * Paystack keeps a payment page open long after the hold lapses, so a
     * payment can land once the hold is gone and the units have been held or
     * sold by someone else. A lapsed (or never taken) hold is not on the shelf
     * any more, so the sale comes out of sellable stock instead, leaving
     * other customers' holds whole. Returns null when there is none: nothing
     * is decremented, and the caller flags the order.
     */
    public function finalize(InventoryItem $item, int $quantity, Order $order): ?InventoryItem
    {
        return DB::transaction(function () use ($item, $quantity, $order) {
            $locked = $this->lock($item->id);

            // An expired hold that the scheduler has not swept yet is lapsed
            // all the same. Sweeping first means it is never mistaken for a
            // live one.
            $this->expireLapsedHolds($locked);

            $holds = $this->activeHolds($locked, $order);
            $held = $holds->sum('quantity');

            if ($held >= $quantity) {
                foreach ($holds as $hold) {
                    $hold->update(['finalized_at' => now()]);
                }

                $locked->quantity_available = max(0, $locked->quantity_available - $quantity);
                $this->recount($locked);
                $this->record($locked, InventoryMovement::SALE, available: -$quantity, reserved: -$held, order: $order);

                return $locked;
            }

            // Release any partial hold first, so its units count as sellable
            // for this sale rather than being lost in the arithmetic.
            foreach ($holds as $hold) {
                $hold->update(['released_at' => now(), 'release_reason' => 'released']);
            }

            if ($held > 0) {
                $this->recount($locked);
            }

            if ($locked->sellable_quantity < $quantity) {
                if ($held > 0) {
                    $this->record($locked, InventoryMovement::HOLD_RELEASED, reserved: -$held, order: $order, note: 'Partial hold released before a shortfall');
                }

                return null;
            }

            $locked->quantity_available -= $quantity;
            $this->recount($locked);
            $this->record($locked, InventoryMovement::SALE, available: -$quantity, reserved: -$held, order: $order, note: 'Paid after the hold lapsed; sold from the shelf');

            return $locked;
        });
    }

    /**
     * Sweep for the ReleaseExpiredReservations scheduled job. Releases every
     * hold past its own expiry and leaves every other hold alone. Returns the
     * number of holds released.
     */
    public function releaseAllExpired(): int
    {
        $released = 0;

        StockHold::query()->active()
            ->where('expires_at', '<=', now())
            ->distinct()
            ->orderBy('inventory_item_id')
            ->pluck('inventory_item_id')
            ->each(function (int $itemId) use (&$released) {
                DB::transaction(function () use ($itemId, &$released) {
                    $released += $this->expireLapsedHolds($this->lock($itemId));
                });
            });

        return $released;
    }

    /**
     * Logs an admin recount. Called by the admin inventory endpoint inside its
     * own transaction, after it has locked and updated the row.
     */
    public function recordAdjustment(InventoryItem $locked, int $previousAvailable, ?int $adminUserId, ?string $note = null): void
    {
        $delta = $locked->quantity_available - $previousAvailable;

        if ($delta === 0) {
            return;
        }

        $this->record($locked, InventoryMovement::ADJUSTMENT, available: $delta, adminUserId: $adminUserId, note: $note);
    }

    private function lock(int $itemId): InventoryItem
    {
        return InventoryItem::query()->lockForUpdate()->findOrFail($itemId);
    }

    /** @return Collection<int, StockHold> */
    private function activeHolds(InventoryItem $locked, Order $order)
    {
        return StockHold::query()->active()
            ->where('inventory_item_id', $locked->id)
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->get();
    }

    /** Releases this item's lapsed holds. The row must already be locked. Returns how many it released. */
    private function expireLapsedHolds(InventoryItem $locked): int
    {
        $lapsed = StockHold::query()->active()
            ->where('inventory_item_id', $locked->id)
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        foreach ($lapsed as $hold) {
            $hold->update(['released_at' => now(), 'release_reason' => 'expired']);
            $this->recount($locked);
            $this->record($locked, InventoryMovement::HOLD_EXPIRED, reserved: -$hold->quantity, orderId: $hold->order_id, channel: $hold->channel);
        }

        return $lapsed->count();
    }

    /** Recomputes the denormalised counters from the active holds, and saves. */
    private function recount(InventoryItem $locked): void
    {
        $active = StockHold::query()->active()->where('inventory_item_id', $locked->id);

        $locked->quantity_reserved = (int) (clone $active)->sum('quantity');
        $locked->reservation_expires_at = $locked->quantity_reserved > 0 ? (clone $active)->max('expires_at') : null;
        $locked->save();
    }

    private function record(
        InventoryItem $locked,
        string $reason,
        int $available = 0,
        int $reserved = 0,
        ?Order $order = null,
        ?int $orderId = null,
        ?string $channel = null,
        ?int $adminUserId = null,
        ?string $note = null,
    ): void {
        InventoryMovement::create([
            'inventory_item_id' => $locked->id,
            'delta_available' => $available,
            'delta_reserved' => $reserved,
            'quantity_available_after' => $locked->quantity_available,
            'quantity_reserved_after' => $locked->quantity_reserved,
            'reason' => $reason,
            'channel' => $channel ?? $order?->channel,
            'order_id' => $orderId ?? $order?->id,
            'admin_user_id' => $adminUserId,
            'note' => $note,
        ]);
    }
}

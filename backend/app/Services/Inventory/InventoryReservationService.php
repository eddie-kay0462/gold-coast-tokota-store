<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InventoryReservationService
{
    /**
     * Soft-reserve `$quantity` units against checkout (Feature 3). Locks the
     * row (SELECT ... FOR UPDATE) so two concurrent requests for the last
     * unit serialize instead of both reading the same sellable count and
     * both succeeding — the correctness guarantee holds regardless of how
     * stale the storefront's polled stock display is.
     *
     * @throws InsufficientStockException
     */
    public function reserve(InventoryItem $item, int $quantity, CarbonInterface $expiresAt): InventoryItem
    {
        return DB::transaction(function () use ($item, $quantity, $expiresAt) {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);

            $this->expireIfPast($locked);

            if ($locked->sellable_quantity < $quantity) {
                throw new InsufficientStockException($locked, $quantity);
            }

            $locked->quantity_reserved += $quantity;
            // The schema has one reservation_expires_at per item, not per
            // reservation, so a second concurrent reservation must extend the
            // clock rather than shorten it — otherwise it could release
            // someone else's still-active hold early.
            $locked->reservation_expires_at = $locked->reservation_expires_at
                ? $locked->reservation_expires_at->max($expiresAt)
                : $expiresAt;
            $locked->save();

            return $locked;
        });
    }

    /** Releases a hold without a sale — payment failed/abandoned before expiry. */
    public function release(InventoryItem $item, int $quantity): InventoryItem
    {
        return DB::transaction(function () use ($item, $quantity) {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $locked->quantity_reserved = max(0, $locked->quantity_reserved - $quantity);

            if ($locked->quantity_reserved === 0) {
                $locked->reservation_expires_at = null;
            }

            $locked->save();

            return $locked;
        });
    }

    /** Payment confirmed — converts a reservation into a real, permanent decrement. */
    public function finalize(InventoryItem $item, int $quantity): InventoryItem
    {
        return DB::transaction(function () use ($item, $quantity) {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $locked->quantity_available = max(0, $locked->quantity_available - $quantity);
            $locked->quantity_reserved = max(0, $locked->quantity_reserved - $quantity);

            if ($locked->quantity_reserved === 0) {
                $locked->reservation_expires_at = null;
            }

            $locked->save();

            return $locked;
        });
    }

    /** Sweep for the ReleaseExpiredReservations scheduled job — releases every item whose hold has expired. */
    public function releaseAllExpired(): int
    {
        $released = 0;

        InventoryItem::query()
            ->where('quantity_reserved', '>', 0)
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', now())
            ->pluck('id')
            ->each(function (int $id) use (&$released) {
                DB::transaction(function () use ($id, &$released) {
                    $locked = InventoryItem::query()->lockForUpdate()->find($id);

                    if ($locked && $this->expireIfPast($locked)) {
                        $released++;
                    }
                });
            });

        return $released;
    }

    /** Lazy expiry check used inside reserve() as a safety net between scheduler ticks. Returns true if it released anything. */
    private function expireIfPast(InventoryItem $item): bool
    {
        if ($item->reservation_expires_at && $item->reservation_expires_at->isPast()) {
            $item->quantity_reserved = 0;
            $item->reservation_expires_at = null;
            $item->save();

            return true;
        }

        return false;
    }
}

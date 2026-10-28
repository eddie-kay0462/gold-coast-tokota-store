<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Jobs\ReleaseExpiredReservations;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\StockHold;
use App\Services\GiftCards\GiftCardService;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reservations = app(InventoryReservationService::class);
    }

    private function order(string $channel = 'web'): Order
    {
        return Order::factory()->create(['channel' => $channel]);
    }

    public function test_reserve_holds_stock_and_sets_an_expiry(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 0]);
        $order = $this->order();

        $result = $this->reservations->reserve($item, 2, now()->addMinutes(15), $order);

        $this->assertSame(2, $result->quantity_reserved);
        $this->assertSame(3, $result->sellable_quantity);
        $this->assertNotNull($result->reservation_expires_at);
        $this->assertSame(1, StockHold::query()->active()->where('order_id', $order->id)->count());
    }

    public function test_reserve_throws_when_requested_quantity_exceeds_sellable_stock(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 2, 'quantity_reserved' => 0]);

        $this->expectException(InsufficientStockException::class);

        $this->reservations->reserve($item, 3, now()->addMinutes(15), $this->order());
    }

    public function test_reserve_accounts_for_units_already_held_by_another_reservation(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 2, 'quantity_reserved' => 0]);
        $this->reservations->reserve($item, 2, now()->addMinutes(10), $this->order());

        $this->expectException(InsufficientStockException::class);

        $this->reservations->reserve($item, 1, now()->addMinutes(15), $this->order());
    }

    public function test_reserve_lazily_releases_an_already_expired_hold_before_checking_capacity(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 2, 'quantity_reserved' => 0]);
        $this->reservations->reserve($item, 2, now()->addMinutes(15), $this->order());

        $this->travel(16)->minutes();

        $result = $this->reservations->reserve($item, 2, now()->addMinutes(15), $this->order());

        $this->assertSame(2, $result->quantity_reserved);
        $this->assertSame(1, StockHold::query()->where('release_reason', 'expired')->count());
    }

    /**
     * The reason holds are per order: a WhatsApp order waiting a day on
     * payment must not keep a 15-minute web checkout's hold alive with it.
     */
    public function test_a_short_hold_and_a_long_hold_on_one_variant_expire_independently(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 0]);
        $web = $this->order('web');
        $whatsapp = $this->order('whatsapp');

        $this->reservations->reserve($item, 1, now()->addMinutes(15), $web);
        $this->reservations->reserve($item, 2, now()->addHours(24), $whatsapp);
        $this->assertSame(3, $item->fresh()->quantity_reserved);

        $this->travel(20)->minutes();
        $this->reservations->releaseAllExpired();

        $this->assertSame(2, $item->fresh()->quantity_reserved);
        $this->assertNotNull(StockHold::query()->where('order_id', $web->id)->sole()->released_at);
        $this->assertNull(StockHold::query()->where('order_id', $whatsapp->id)->sole()->released_at);

        $this->travel(24)->hours();
        $this->reservations->releaseAllExpired();

        $this->assertSame(0, $item->fresh()->quantity_reserved);
        $this->assertNull($item->fresh()->reservation_expires_at);
    }

    public function test_release_order_frees_only_that_orders_holds(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 0]);
        $mine = $this->order();
        $theirs = $this->order();
        $this->reservations->reserve($item, 2, now()->addMinutes(10), $mine);
        $this->reservations->reserve($item, 1, now()->addMinutes(10), $theirs);

        $released = $this->reservations->releaseOrder($mine);

        $this->assertSame(2, $released);
        $this->assertSame(5, $item->fresh()->quantity_available);
        $this->assertSame(1, $item->fresh()->quantity_reserved);
    }

    public function test_finalize_permanently_decrements_available_and_clears_the_hold(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 0]);
        $order = $this->order();
        $this->reservations->reserve($item, 2, now()->addMinutes(10), $order);

        $result = $this->reservations->finalize($item, 2, $order);

        $this->assertSame(3, $result->quantity_available);
        $this->assertSame(0, $result->quantity_reserved);
        $this->assertNull($result->reservation_expires_at);
        $this->assertNotNull(StockHold::query()->where('order_id', $order->id)->sole()->finalized_at);
    }

    public function test_finalize_with_a_lapsed_hold_sells_from_the_shelf_and_leaves_other_holds_whole(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 3, 'quantity_reserved' => 0]);
        $late = $this->order();
        $this->reservations->reserve($item, 1, now()->addMinutes(15), $late);
        $this->travel(16)->minutes();
        $this->reservations->reserve($item, 1, now()->addMinutes(15), $this->order());

        $result = $this->reservations->finalize($item, 1, $late);

        $this->assertSame(2, $result->quantity_available);
        $this->assertSame(1, $result->quantity_reserved);
    }

    public function test_finalize_returns_null_when_a_lapsed_hold_has_nothing_left_behind_it(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 1, 'quantity_reserved' => 0]);
        $late = $this->order();
        $this->reservations->reserve($item, 1, now()->addMinutes(15), $late);
        $this->travel(16)->minutes();
        $this->reservations->reserve($item, 1, now()->addMinutes(15), $this->order());

        $this->assertNull($this->reservations->finalize($item, 1, $late));
        $this->assertSame(1, $item->fresh()->quantity_available);
        $this->assertSame(1, $item->fresh()->quantity_reserved);
    }

    public function test_release_all_expired_only_touches_holds_past_their_expiry(): void
    {
        $expired = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 0]);
        $active = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 0]);
        $untouched = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 0]);
        $this->reservations->reserve($expired, 3, now()->addMinutes(1), $this->order());
        $this->reservations->reserve($active, 2, now()->addMinutes(30), $this->order());

        $this->travel(5)->minutes();
        $released = $this->reservations->releaseAllExpired();

        $this->assertSame(1, $released);
        $this->assertSame(0, $expired->fresh()->quantity_reserved);
        $this->assertSame(2, $active->fresh()->quantity_reserved);
        $this->assertSame(0, $untouched->fresh()->quantity_reserved);
    }

    public function test_release_expired_reservations_job_delegates_to_the_service(): void
    {
        $expired = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 0]);
        $this->reservations->reserve($expired, 3, now()->addMinute(), $this->order());
        $this->travel(2)->minutes();

        (new ReleaseExpiredReservations)->handle(app(InventoryReservationService::class), app(GiftCardService::class));

        $this->assertSame(0, $expired->fresh()->quantity_reserved);
        $this->assertNull($expired->fresh()->reservation_expires_at);
    }

    public function test_every_change_is_written_to_the_movement_ledger(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 0]);
        $sold = $this->order('whatsapp');
        $abandoned = $this->order();
        $lapsed = $this->order();

        $this->reservations->reserve($item, 2, now()->addHours(24), $sold);
        $this->reservations->reserve($item, 1, now()->addMinutes(15), $abandoned);
        $this->reservations->reserve($item, 1, now()->addMinutes(5), $lapsed);
        $this->reservations->releaseOrder($abandoned);
        $this->travel(10)->minutes();
        $this->reservations->releaseAllExpired();
        $this->reservations->finalize($item, 2, $sold);

        $reasons = InventoryMovement::query()->where('inventory_item_id', $item->id)->orderBy('id')->pluck('reason')->all();
        $this->assertSame(['hold', 'hold', 'hold', 'hold_released', 'hold_expired', 'sale'], $reasons);

        $sale = InventoryMovement::query()->where('reason', 'sale')->sole();
        $this->assertSame(-2, $sale->delta_available);
        $this->assertSame(-2, $sale->delta_reserved);
        $this->assertSame(3, $sale->quantity_available_after);
        $this->assertSame(0, $sale->quantity_reserved_after);
        $this->assertSame('whatsapp', $sale->channel);
        $this->assertSame($sold->id, $sale->order_id);
    }
}

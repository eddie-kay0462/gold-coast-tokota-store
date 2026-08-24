<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Jobs\ReleaseExpiredReservations;
use App\Models\InventoryItem;
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

    public function test_reserve_holds_stock_and_sets_an_expiry(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 0]);

        $result = $this->reservations->reserve($item, 2, now()->addMinutes(15));

        $this->assertSame(2, $result->quantity_reserved);
        $this->assertSame(3, $result->sellable_quantity);
        $this->assertNotNull($result->reservation_expires_at);
    }

    public function test_reserve_throws_when_requested_quantity_exceeds_sellable_stock(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 2, 'quantity_reserved' => 0]);

        $this->expectException(InsufficientStockException::class);

        $this->reservations->reserve($item, 3, now()->addMinutes(15));
    }

    public function test_reserve_accounts_for_units_already_held_by_another_reservation(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 2, 'quantity_reserved' => 2, 'reservation_expires_at' => now()->addMinutes(10)]);

        $this->expectException(InsufficientStockException::class);

        $this->reservations->reserve($item, 1, now()->addMinutes(15));
    }

    public function test_reserve_extends_rather_than_shortens_an_existing_expiry(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 1, 'reservation_expires_at' => now()->addMinutes(20)]);
        // reservation_expires_at truncates to whole seconds in Postgres — read
        // it back post-insert so the comparison isn't off by sub-second noise.
        $laterExpiry = $item->fresh()->reservation_expires_at;

        $result = $this->reservations->reserve($item, 1, now()->addMinutes(5));

        $this->assertTrue($result->reservation_expires_at->equalTo($laterExpiry));
    }

    public function test_reserve_lazily_clears_an_already_expired_reservation_before_checking_capacity(): void
    {
        $item = InventoryItem::factory()->create([
            'quantity_available' => 2,
            'quantity_reserved' => 2,
            'reservation_expires_at' => now()->subMinute(),
        ]);

        $result = $this->reservations->reserve($item, 2, now()->addMinutes(15));

        $this->assertSame(2, $result->quantity_reserved);
    }

    public function test_release_frees_held_stock_without_decrementing_available(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 2, 'reservation_expires_at' => now()->addMinutes(10)]);

        $result = $this->reservations->release($item, 2);

        $this->assertSame(5, $result->quantity_available);
        $this->assertSame(0, $result->quantity_reserved);
        $this->assertNull($result->reservation_expires_at);
    }

    public function test_finalize_permanently_decrements_available_and_clears_the_reservation(): void
    {
        $item = InventoryItem::factory()->create(['quantity_available' => 5, 'quantity_reserved' => 2, 'reservation_expires_at' => now()->addMinutes(10)]);

        $result = $this->reservations->finalize($item, 2);

        $this->assertSame(3, $result->quantity_available);
        $this->assertSame(0, $result->quantity_reserved);
        $this->assertNull($result->reservation_expires_at);
    }

    public function test_release_all_expired_only_touches_items_past_their_expiry(): void
    {
        $expired = InventoryItem::factory()->create(['quantity_reserved' => 3, 'reservation_expires_at' => now()->subMinute()]);
        $active = InventoryItem::factory()->create(['quantity_reserved' => 2, 'reservation_expires_at' => now()->addMinutes(10)]);
        $untouched = InventoryItem::factory()->create(['quantity_reserved' => 0, 'reservation_expires_at' => null]);

        $released = $this->reservations->releaseAllExpired();

        $this->assertSame(1, $released);
        $this->assertSame(0, $expired->fresh()->quantity_reserved);
        $this->assertSame(2, $active->fresh()->quantity_reserved);
        $this->assertSame(0, $untouched->fresh()->quantity_reserved);
    }

    public function test_release_expired_reservations_job_delegates_to_the_service(): void
    {
        $expired = InventoryItem::factory()->create(['quantity_reserved' => 3, 'reservation_expires_at' => now()->subMinute()]);

        (new ReleaseExpiredReservations)->handle(app(InventoryReservationService::class));

        $this->assertSame(0, $expired->fresh()->quantity_reserved);
        $this->assertNull($expired->fresh()->reservation_expires_at);
    }
}

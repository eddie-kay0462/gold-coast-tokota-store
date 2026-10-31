<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditEntry;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\WhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The dashboard's "Recent activity" and Settings › Audit log.
 *
 * Activity is read from what is already recorded (orders, bookings, stock
 * recounts, WhatsApp messages, posts, customer accounts), so it needs no
 * table of its own and can't drift from them. The audit log is the admin's
 * own changes, written by RecordAdminAction.
 */
class ActivityController extends Controller
{
    private const PER_SOURCE = 8;

    public function activity(): JsonResponse
    {
        $items = collect()
            ->merge($this->orders())
            ->merge($this->bookings())
            ->merge($this->stock())
            ->merge($this->messages())
            ->merge($this->posts())
            ->merge($this->customers())
            ->sortByDesc('at')
            ->take(20)
            ->values()
            ->map(fn (array $item, int $i) => ['id' => $i + 1, ...$item]);

        return response()->json(['data' => $items]);
    }

    public function audit(Request $request): JsonResponse
    {
        $page = AdminAuditEntry::query()
            ->latest('id')
            ->paginate(max(1, min($request->integer('per_page', 200), 500)));

        return response()->json([
            'data' => collect($page->items())->map(fn (AdminAuditEntry $e) => [
                'id' => $e->id,
                'actor_name' => $e->actor_name,
                'actor_role' => $e->actor_role,
                'action' => $e->action,
                'route' => $e->route,
                'target' => $e->target ?? '',
                'detail' => $e->detail ?? '',
                'at' => $e->created_at,
            ]),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'per_page' => $page->perPage()],
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function orders(): Collection
    {
        return Order::query()->with('customer')
            ->whereNotIn('status', ['pending', 'cancelled'])
            ->latest()->limit(self::PER_SOURCE)->get()
            ->map(fn (Order $o) => $this->item(
                'order',
                ($o->kind === 'gift_card' ? 'Gift card bought: ' : 'Order ').$o->reference
                    .' · '.$o->currency.' '.number_format($o->total / 100, 2),
                $o->customer?->name ?? ($o->shipping_address['full_name'] ?? null),
                $o->created_at,
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function bookings(): Collection
    {
        return Booking::query()->with(['customer', 'workshopSession.workshopType'])
            ->latest()->limit(self::PER_SOURCE)->get()
            ->map(fn (Booking $b) => $this->item(
                'booking',
                $b->type === 'workshop'
                    ? 'Booked '.($b->workshopSession?->workshopType?->name ?? 'a workshop')
                    : 'DIY order submitted',
                $b->customer?->name ?? ($b->details['name'] ?? null),
                $b->created_at,
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function stock(): Collection
    {
        return InventoryMovement::query()->with(['inventoryItem.product', 'adminUser'])
            ->where('reason', 'adjustment')
            ->latest()->limit(self::PER_SOURCE)->get()
            ->map(function (InventoryMovement $m) {
                $item = $m->inventoryItem;
                $variant = collect($item?->variant_attributes ?? [])->join(' ');

                return $this->item(
                    'stock',
                    'Stock recounted: '.trim(($item?->product?->name ?? 'a product').' '.$variant)." → {$m->quantity_available_after}",
                    $m->adminUser?->name,
                    $m->created_at,
                    $m->adminUser?->avatar,
                );
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function messages(): Collection
    {
        return WhatsAppMessage::query()->with('conversation.contact.customer')
            ->where('direction', 'inbound')
            ->latest()->limit(self::PER_SOURCE)->get()
            ->map(fn (WhatsAppMessage $m) => $this->item(
                'message',
                'WhatsApp message',
                $m->conversation?->contact?->displayName(),
                $m->sent_at ?? $m->created_at,
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function posts(): Collection
    {
        return BlogPost::query()->published()
            ->latest('published_at')->limit(self::PER_SOURCE)->get()
            ->map(fn (BlogPost $p) => $this->item('content', "Story published: {$p->title}", $p->author, $p->published_at));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function customers(): Collection
    {
        return Customer::query()->latest()->limit(self::PER_SOURCE)->get()
            ->map(fn (Customer $c) => $this->item('customer', 'New customer account', $c->name, $c->created_at));
    }

    /** @return array<string, mixed> */
    private function item(string $kind, string $title, ?string $actor, $at, ?string $avatar = null): array
    {
        return ['kind' => $kind, 'title' => $title, 'actor' => $actor ?: null, 'avatar' => $avatar, 'at' => $at];
    }
}

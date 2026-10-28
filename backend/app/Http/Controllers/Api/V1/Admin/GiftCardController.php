<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGiftCardRequest;
use App\Http\Requests\Admin\UpdateGiftCardRequest;
use App\Http\Requests\Admin\UpdateGiftCardSettingsRequest;
use App\Http\Resources\Admin\AdminGiftCardResource;
use App\Jobs\SendGiftCardNotification;
use App\Models\GiftCard;
use App\Models\SiteSetting;
use App\Services\GiftCards\GiftCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Gift cards and store credit in the admin: find a card, see its ledger,
 * issue store credit, disable or correct a card, resend its email, and set
 * the policy (on sale or not, amounts, expiry, terms).
 */
class GiftCardController extends Controller
{
    public function __construct(private readonly GiftCardService $giftCards) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('q', ''));

        $cards = GiftCard::query()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.mb_strtolower($search).'%';
                $code = GiftCard::normalise($search);
                $query->where(fn ($q) => $q
                    ->whereRaw('lower(recipient_email) like ?', [$like])
                    ->orWhereRaw('lower(recipient_name) like ?', [$like])
                    ->orWhere('code', 'like', '%'.($code !== '' ? $code : $search).'%'));
            })
            ->when($request->query('source'), fn ($q, $source) => $q->where('source', $source))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->with(['purchaseOrder', 'issuedBy'])
            ->latest('id')
            // The admin filters and pages in the browser, so it asks for a
            // generous page.
            ->paginate(min(max((int) $request->query('per_page', 25), 1), 200));

        return AdminGiftCardResource::collection($cards);
    }

    public function show(GiftCard $giftCard): AdminGiftCardResource
    {
        return new AdminGiftCardResource($giftCard->load(['purchaseOrder', 'issuedBy', 'transactions.order', 'transactions.admin']));
    }

    /** Store credit, issued by an Admin. */
    public function store(StoreGiftCardRequest $request): JsonResponse
    {
        $data = $request->validated();

        $card = $this->giftCards->issue(
            $data['amount'],
            GiftCard::SOURCE_STORE_CREDIT,
            [
                'recipient_name' => $data['recipient_name'],
                'recipient_email' => $data['recipient_email'] ?? null,
                'note' => $data['note'],
            ],
            by: $request->user('admin'),
        );

        if (($data['send_email'] ?? false) && $card->recipient_email) {
            SendGiftCardNotification::dispatch($card);
        }

        return (new AdminGiftCardResource($card->load(['issuedBy', 'transactions'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateGiftCardRequest $request, GiftCard $giftCard): AdminGiftCardResource
    {
        $data = $request->validated();

        if (isset($data['status'])) {
            $giftCard->update(['status' => $data['status']]);
        }

        if (array_key_exists('balance', $data)) {
            $this->giftCards->adjust($giftCard, $data['balance'], $request->user('admin'), $data['note']);
        }

        return $this->show($giftCard->fresh());
    }

    /** Sends the card's email again, to the address already on it. */
    public function resend(GiftCard $giftCard): JsonResponse
    {
        if (! $giftCard->recipient_email) {
            return response()->json(['message' => 'This card has no email address to send to.'], 422);
        }

        SendGiftCardNotification::dispatch($giftCard);

        return response()->json(['message' => "Sent to {$giftCard->recipient_email}."]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => $this->giftCards->settings()]);
    }

    public function updateSettings(UpdateGiftCardSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $amounts = array_map('intval', $data['amounts'] ?? []);
        sort($amounts);

        SiteSetting::current()->update([
            'gift_cards_enabled' => $data['enabled'],
            'gift_card_amounts' => $amounts,
            'gift_card_expiry_months' => $data['expiry_months'] ?? null,
            'gift_card_terms' => $data['terms'] ?? null,
        ]);

        return response()->json(['data' => $this->giftCards->settings()]);
    }
}

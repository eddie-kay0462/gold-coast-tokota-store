<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReturnRequestRequest;
use App\Http\Requests\Admin\UpdateReturnRequestRequest;
use App\Http\Resources\Admin\ReturnRequestResource;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\Returns\ReturnPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Returns and exchanges (§9, §21).
 *
 * Staff record and track them; resolving one is Admin work, because every
 * resolution is a refund, a replacement or a refusal. There is no customer
 * endpoint — §9 gives WhatsApp as the single contact route for a return, so a
 * request arrives as a message and is entered here.
 */
class ReturnController extends Controller
{
    public function __construct(private readonly ReturnPolicy $policy) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $returns = ReturnRequest::query()
            ->with(['order.items', 'order.customer', 'resolvedByAdmin'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->string('reason')))
            // Unresolved first, then newest: the screen exists to work a
            // queue, and a resolved request is history.
            ->orderByRaw('resolved_at is null desc')
            ->orderByDesc('requested_at')
            ->paginate(25)
            ->withQueryString();

        return ReturnRequestResource::collection($returns);
    }

    public function show(ReturnRequest $returnRequest): ReturnRequestResource
    {
        return new ReturnRequestResource(
            $returnRequest->load(['order.items', 'order.customer', 'resolvedByAdmin'])
        );
    }

    public function store(StoreReturnRequestRequest $request): ReturnRequestResource
    {
        $order = Order::query()
            ->with('items.product')
            ->where('reference', $request->validated('order_reference'))
            ->firstOrFail();

        $assessment = $this->policy->assess($order, $request->validated('reason'));

        $returnRequest = ReturnRequest::create([
            'order_id' => $order->id,
            'reason' => $request->validated('reason'),
            'status' => 'requested',
            'notes' => $request->validated('notes'),
            'requested_at' => now(),
            ...$assessment,
        ]);

        return new ReturnRequestResource(
            $returnRequest->load(['order.items', 'order.customer'])
        );
    }

    public function update(UpdateReturnRequestRequest $request, ReturnRequest $returnRequest): JsonResponse|ReturnRequestResource
    {
        if (in_array($returnRequest->status, ReturnRequest::RESOLVED_STATUSES, true)) {
            return response()->json([
                'message' => 'This return has already been resolved. Raise a new request rather than reopening it.',
            ], 422);
        }

        $data = $request->validated();
        $isResolution = in_array($data['status'], ReturnRequest::RESOLVED_STATUSES, true);

        $returnRequest->update([
            ...$data,
            'resolved_at' => $isResolution ? now() : null,
            'resolved_by_admin_id' => $isResolution ? $request->user('admin')->id : null,
        ]);

        // A refunded return means the order was refunded. Leaving the order at
        // `delivered` would let the two screens disagree about the same money.
        //
        // This records the decision; it does not move funds. No gateway refund
        // call exists yet — see PaystackService — so the transfer itself is
        // still made in the Paystack dashboard.
        if ($data['status'] === 'refunded') {
            $returnRequest->order->update(['status' => 'refunded']);
        }

        return new ReturnRequestResource(
            $returnRequest->fresh(['order.items', 'order.customer', 'resolvedByAdmin'])
        );
    }
}

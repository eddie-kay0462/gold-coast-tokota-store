<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsletterSubscriptionRequest;
use App\Http\Resources\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;

class NewsletterSubscriptionController extends Controller
{
    /**
     * Single opt-in — no confirmation step, so a repeat submission for an
     * already-subscribed email is treated as a no-op success, not a 422.
     */
    public function store(StoreNewsletterSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $subscriber = NewsletterSubscriber::query()->firstOrCreate(
            ['email' => $data['email']],
            ['subscribed_at' => now(), 'source' => $data['source'] ?? null],
        );

        return (new NewsletterSubscriberResource($subscriber))
            ->response()
            ->setStatusCode($subscriber->wasRecentlyCreated ? 201 : 200);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Newsletter subscribers (README Feature 9) — read-only for Admin and Staff,
 * with the export the spec asks for.
 */
class NewsletterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $subscribers = NewsletterSubscriber::query()
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')))
            ->latest('subscribed_at')
            ->paginate(50)
            ->withQueryString();

        return NewsletterSubscriberResource::collection($subscribers);
    }

    /**
     * CSV export. Streamed rather than built in memory: this list only grows,
     * and the day it is big enough to matter is not the day to find out.
     */
    public function export(): StreamedResponse
    {
        $filename = 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['email', 'source', 'subscribed_at']);

            NewsletterSubscriber::query()
                ->orderBy('id')
                ->chunk(500, function ($subscribers) use ($handle) {
                    foreach ($subscribers as $subscriber) {
                        fputcsv($handle, [
                            $subscriber->email,
                            $subscriber->source,
                            $subscriber->subscribed_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

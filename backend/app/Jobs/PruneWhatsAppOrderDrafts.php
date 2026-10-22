<?php

namespace App\Jobs;

use App\Models\WhatsAppOrderDraft;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Deletes "Order on WhatsApp" carts nobody turned into an order within their
 * seven days. Converted ones stay: they record where an order came from.
 */
class PruneWhatsAppOrderDrafts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        WhatsAppOrderDraft::query()
            ->whereNull('converted_order_id')
            ->where('expires_at', '<=', now())
            ->delete();
    }
}

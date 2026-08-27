<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeedbackResource;
use App\Models\Feedback;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only feedback list for admin (README Feature 9 — Admin + Staff, with
 * export). There is no update or delete: feedback is a record of what someone
 * said, and an admin panel that can quietly edit it is worse than one that
 * cannot.
 */
class FeedbackController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $entries = Feedback::query()
            ->latest()
            ->paginate(50);

        return FeedbackResource::collection($entries);
    }
}

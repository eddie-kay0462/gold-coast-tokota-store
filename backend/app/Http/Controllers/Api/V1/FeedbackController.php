<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;

class FeedbackController extends Controller
{
    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        Feedback::create([
            ...$request->validated(),
            // Attached when there's a session, so admin can see that a piece of
            // feedback came from a known customer. Never taken from the body.
            'customer_id' => $request->user()?->id,
        ]);

        // 201 with no body: the form replaces itself with a thank-you and has
        // nothing to render from the response.
        return response()->json(['message' => 'Thanks for your feedback.'], 201);
    }
}

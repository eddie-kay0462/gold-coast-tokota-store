<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'rating' => $this->rating,
            'message' => $this->message,
            // Named for what it means to a reader of the admin table, not for
            // the column it happens to live in — admin's FeedbackEntry expects
            // `submittedAt`, which its snake_case normaliser derives from this.
            'submitted_at' => $this->created_at,
        ];
    }
}

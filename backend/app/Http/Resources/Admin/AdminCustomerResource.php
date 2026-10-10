<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'preferred_currency' => $this->preferred_currency,
            // Whether they ever set a password. Guest checkout means a Customer
            // row can exist without one, so "has an account" and "has ordered"
            // are different questions.
            'has_account' => $this->password !== null,
            'orders_count' => $this->whenCounted('orders'),
            'registered_at' => $this->created_at,
        ];
    }
}

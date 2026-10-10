<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Staff can move an order through fulfilment. Only an Admin can refund one
     * — the README's role rule names refunds explicitly alongside pricing and
     * site settings, and §18 of the brand document keeps money away from the
     * Staff tier.
     *
     * Enforced here rather than in middleware because it depends on the
     * *value* being submitted, not on the route: the same endpoint is legal for
     * Staff right up until they ask for `refunded`.
     */
    public function authorize(): bool
    {
        if ($this->input('status') !== 'refunded') {
            return true;
        }

        return (bool) $this->user('admin')?->hasCapability('orders.refund');
    }

    protected function failedAuthorization(): void
    {
        // A sentence, not a raw 403 blob — the README's edge case asks for a
        // clear, non-technical message here.
        abort(403, AdminCapability::denialMessage(
            'orders.refund',
            $this->user('admin')?->role ?? 'intern',
        ));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                'pending', 'paid', 'processing', 'shipped',
                'delivered', 'cancelled', 'refunded', 'inventory_conflict',
            ])],
        ];
    }
}

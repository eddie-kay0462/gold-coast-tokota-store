<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Staff can move an order through fulfilment. Only an Admin can refund one
     * — the README's two-tier rule names refunds explicitly alongside pricing
     * and site settings.
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

        return $this->user('admin')?->role === 'admin';
    }

    protected function failedAuthorization(): void
    {
        // A sentence, not a raw 403 blob — the README's edge case asks for a
        // clear, non-technical message here.
        abort(403, 'Refunds can only be issued by an admin. Ask an administrator to complete this refund.');
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

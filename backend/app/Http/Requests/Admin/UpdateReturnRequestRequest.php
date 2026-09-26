<?php

namespace App\Http\Requests\Admin;

use App\Models\ReturnRequest;
use App\Support\AdminCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReturnRequestRequest extends FormRequest
{
    /**
     * Resolving a return as refunded is issuing a refund, so it needs the same
     * capability the order endpoint asks for. The route already restricts this
     * to `returns.resolve`; this is the value-dependent half — the same
     * pattern UpdateOrderStatusRequest uses for `status = refunded`.
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
        abort(403, AdminCapability::denialMessage(
            'orders.refund',
            $this->user('admin')?->role ?? 'intern',
        ));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ReturnRequest::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Staff judgement overrides the automatic assessment — §9's
            // "products damaged through misuse" is a call a person makes on
            // seeing the pair, and no rule can make it from the order alone.
            'is_eligible' => ['sometimes', 'boolean'],
            'ineligible_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

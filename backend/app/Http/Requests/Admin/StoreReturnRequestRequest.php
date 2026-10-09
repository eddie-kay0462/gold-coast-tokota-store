<?php

namespace App\Http\Requests\Admin;

use App\Services\Returns\ReturnPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recording a return or exchange that came in over WhatsApp (§9 names it as
 * the contact route), so the fields are the ones a member of staff can read
 * off a conversation: which order, why, and what was said.
 *
 * Eligibility is not accepted from the client. It is decided by ReturnPolicy
 * against the order — a request that could set `is_eligible` itself would make
 * the policy advisory.
 */
class StoreReturnRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // By reference, the number the customer quotes over WhatsApp —
            // the numeric id is not something they can read off anything.
            'order_reference' => ['required', 'string', 'exists:orders,reference'],
            'reason' => ['required', Rule::in(ReturnPolicy::REASONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

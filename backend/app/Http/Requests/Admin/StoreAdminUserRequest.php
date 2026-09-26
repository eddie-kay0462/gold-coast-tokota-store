<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creating and editing admin accounts.
 *
 * Super Admin only, enforced by the route's `capability:team.manage`
 * middleware: §18 of the brand document puts users and system-level settings
 * outside the Admin tier, and an Admin able to promote themselves would make
 * that line decorative.
 */
class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $existing = $this->route('adminUser');
        $userId = $existing?->id;

        // The role that will be in force after this request, not the one in
        // the payload: an update that only extends an intern's access sends no
        // `role` at all, and comparing against a missing field would refuse
        // the one edit this field exists for.
        $effectiveRole = $this->input('role', $existing?->role);

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            // §17 lists one against every person on the roster. Optional
            // because it is a label, not an access decision.
            'job_title' => ['nullable', 'string', 'max:255'],
            'email' => [
                $creating ? 'required' : 'sometimes', 'email', 'max:255',
                Rule::unique('admin_users', 'email')->ignore($userId),
            ],
            'password' => [$creating ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'role' => [$creating ? 'required' : 'sometimes', Rule::in(AdminCapability::ROLES)],

            // An intern account without an expiry is just a weaker staff
            // account — the whole point of the tier is that it lapses. And an
            // expiry on any other tier would silently lock out a permanent
            // member of staff, so it is refused rather than ignored.
            'access_expires_at' => [
                Rule::requiredIf(fn () => $creating && $effectiveRole === 'intern'),
                Rule::prohibitedIf(fn () => $effectiveRole !== 'intern'),
                'nullable',
                'date',
                'after:now',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'access_expires_at.required' => 'An intern account needs an access expiry date.',
            'access_expires_at.prohibited' => 'Only intern accounts have a time limit on their access.',
            'access_expires_at.after' => 'The access expiry has to be in the future.',
        ];
    }
}

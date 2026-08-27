<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creating and editing Admin/Staff accounts.
 *
 * Admin-only, enforced by the route's `admin` middleware: deciding who has
 * access is the most privileged action in the system, and a Staff user able to
 * create an Admin would make the two-tier rule decorative.
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
        $userId = $this->route('adminUser')?->id;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [
                $creating ? 'required' : 'sometimes', 'email', 'max:255',
                Rule::unique('admin_users', 'email')->ignore($userId),
            ],
            'password' => [$creating ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'role' => [$creating ? 'required' : 'sometimes', Rule::in(['admin', 'staff'])],
        ];
    }
}

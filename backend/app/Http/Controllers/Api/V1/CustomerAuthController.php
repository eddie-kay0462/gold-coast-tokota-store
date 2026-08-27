<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\RegisterCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Customer sessions on the `web` guard (README Feature 4 — guest checkout is
 * supported, so an account is optional throughout).
 *
 * Sanctum SPA cookie auth, the same mechanism AdminAuthController uses against
 * the `admin` guard. The storefront must call `GET /sanctum/csrf-cookie` first
 * and send `credentials: 'include'` — `composables/useAuth.ts` already
 * documents both steps.
 */
class CustomerAuthController extends Controller
{
    public function register(RegisterCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        // Signed straight in: making someone register and then immediately log
        // in with the credentials they just typed is friction for no security.
        Auth::guard('web')->login($customer);
        $request->session()->regenerate();

        return response()->json(['data' => new CustomerResource($customer)], 201);
    }

    public function login(CustomerLoginRequest $request): JsonResponse
    {
        $request->authenticate();

        // Regenerated on privilege change, so a session id captured before
        // sign-in cannot be replayed afterwards (session fixation).
        $request->session()->regenerate();

        return response()->json(['data' => new CustomerResource($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        // Both, and in this order: invalidate() drops the session data,
        // regenerateToken() stops the old CSRF token staying valid.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request): CustomerResource
    {
        return new CustomerResource($request->user());
    }
}

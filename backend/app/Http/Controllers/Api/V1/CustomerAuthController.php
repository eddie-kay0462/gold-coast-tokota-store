<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\RegisterCustomerRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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

    /**
     * Start a password reset.
     *
     * **Always answers the same thing**, whether or not the address belongs to
     * an account. Reporting "no such account" would turn this into an email
     * enumeration oracle — a way to test which of a list of addresses shops
     * here — and that is a customer-privacy leak, not just an auth detail.
     * The broker's own per-email throttle still applies underneath, so
     * repeated requests for a real address do not send repeated emails.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker('customers')->sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If that email has an account, a reset link is on its way.',
        ]);
    }

    /**
     * Finish a password reset.
     *
     * Unlike the request step this **does** distinguish failure, because by
     * now the customer is holding a link we sent them: an expired or already-
     * used token has to say so, or they will retype the same dead link and
     * wonder why nothing happens. It reveals nothing new — anyone with the
     * token already had the email.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($customer, string $password) {
                $customer->forceFill([
                    'password' => Hash::make($password),
                    // A new remember token invalidates "remember me" cookies
                    // issued before the reset. Someone resetting a password
                    // may be doing it *because* an old session is not theirs.
                    'remember_token' => Str::random(60),
                ])->save();

                // Fired by the application, not by the broker — Laravel's own
                // starter kits do it here too. Anything that needs to react to
                // a credential change (session pruning, a security email)
                // hangs off this rather than off the controller.
                event(new PasswordReset($customer));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'That reset link is invalid or has expired. Please request a new one.',
            ], 422);
        }

        return response()->json(['message' => 'Your password has been reset. You can sign in now.']);
    }
}

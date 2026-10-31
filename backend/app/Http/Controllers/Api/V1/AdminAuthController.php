<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\AdminUserResource;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AdminAuthController extends Controller
{
    /**
     * Authenticate an Admin/Staff user and start a Sanctum SPA session.
     * Session regeneration guards against fixation across the login boundary.
     */
    public function login(AdminLoginRequest $request): AdminUserResource
    {
        $request->authenticate();
        $request->session()->regenerate();

        // The team screen shows it, and a dormant account holding standing
        // access is worth being able to see.
        $request->user('admin')->forceFill(['last_active_at' => now()])->save();

        return new AdminUserResource($request->user('admin'));
    }

    public function logout(Request $request): Response
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /** Lets the admin app resolve the current session + role on load/refresh. */
    public function me(Request $request): AdminUserResource
    {
        return new AdminUserResource($request->user('admin'));
    }

    /**
     * Your own name, job title, email and password (Account in the admin).
     * Role and access dates are not here: those are the Super Admin's, on
     * the team page. Changing the email or password needs the current
     * password, so an unattended signed-in screen can't be taken over.
     */
    public function updateMe(Request $request): AdminUserResource
    {
        $admin = $request->user('admin');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('admin_users', 'email')->ignore($admin->id)],
            'password' => ['sometimes', 'required', 'confirmed', PasswordRule::defaults()],
            'current_password' => [
                Rule::requiredIf(fn () => $request->filled('password')
                    || ($request->filled('email') && $request->input('email') !== $admin->email)),
                'nullable', 'current_password:admin',
            ],
        ], [
            'current_password.current_password' => 'That isn’t your current password.',
            'current_password.required' => 'Enter your current password to change your email or password.',
        ]);

        unset($data['current_password']);
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $admin->update($data);

        return new AdminUserResource($admin->fresh());
    }

    /** The same no-enumeration answer as the customer's forgot-password. */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker('admins')->sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If that email has a dashboard account, a reset link is on its way.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($admin, string $password) {
                $admin->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($admin));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'That reset link is invalid or has expired. Ask for a new one.',
            ], 422);
        }

        return response()->json(['message' => 'Your password has been reset. You can sign in now.']);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\AdminUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

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
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffOrAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if (! $admin || ! in_array($admin->role, ['admin', 'staff'], true)) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => ['message' => 'This action requires staff or admin access.'],
            ], 403);
        }

        return $next($request);
    }
}

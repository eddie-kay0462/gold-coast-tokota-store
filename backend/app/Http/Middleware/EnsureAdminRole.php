<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if (! $admin || $admin->role !== 'admin') {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => ['message' => 'This action requires admin access.'],
            ], 403);
        }

        return $next($request);
    }
}

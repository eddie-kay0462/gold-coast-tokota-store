<?php

namespace App\Http\Middleware;

use App\Support\AdminCapability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The role boundary, expressed as the capability a route needs rather than the
 * tiers allowed to reach it.
 *
 * This replaced `EnsureAdminRole`/`EnsureStaffOrAdminRole`, which could only
 * ask "admin or not". The brand document's permission model (§18) does not
 * divide that way: an Admin may change prices and issue refunds but not touch
 * payment credentials, and a Staff member may adjust stock but not price. Both
 * of those live on the same two-tier side of the old middleware, so neither
 * could be enforced.
 *
 * Usage: `->middleware('capability:orders.view')`, or several separated by
 * commas, in which case **all** are required.
 */
class EnsureCapability
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$capabilities): Response
    {
        $admin = $request->user('admin');

        if (! $admin) {
            return $this->deny('This action requires an admin or staff account.');
        }

        if ($admin->accessHasLapsed()) {
            return $this->deny(
                'Your access expired on '.$admin->access_expires_at->toFormattedDateString().
                '. Ask an Admin to extend it.'
            );
        }

        foreach ($capabilities as $capability) {
            if (! $admin->hasCapability($capability)) {
                // The specific copy, not a generic 403: README Feature 9
                // requires the person to be told what they need, and the
                // frontend renders this string as-is.
                return $this->deny(AdminCapability::denialMessage($capability, $admin->role));
            }
        }

        return $next($request);
    }

    private function deny(string $message): Response
    {
        return response()->json([
            'data' => null,
            'meta' => null,
            'message' => $message,
            'errors' => ['message' => $message],
        ], 403);
    }
}

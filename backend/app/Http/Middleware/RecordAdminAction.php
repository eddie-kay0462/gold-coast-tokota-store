<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditEntry;
use App\Models\AdminUser;
use App\Models\GiftCard;
use App\Support\AdminCapability;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Writes Settings › Audit log: every change made in the admin that
 * succeeded, with who made it and what it touched.
 *
 * Recorded from the route rather than in each controller, so a new admin
 * endpoint is logged without anyone remembering to. The detail is the names
 * of the fields sent, plus a status when one is set, never their values: a
 * password, a message to a customer or a page body has no place in a log.
 *
 * A failure to log never fails the change it describes.
 */
class RecordAdminAction
{
    /** Writes that aren't changes worth an entry. */
    private const SKIP = ['logout', 'read', 'render', 'drafts.store'];

    /** Field names that are never written down, even as names. */
    private const SECRET = ['password', 'password_confirmation', 'current_password', 'token'];

    private const VERBS = [
        'store' => 'Added', 'update' => 'Updated', 'destroy' => 'Deleted',
        'resend' => 'Resent', 'cancel' => 'Cancelled', 'pack' => 'Packed',
        'manual-dispatch' => 'Dispatched', 'mark-delivered' => 'Marked delivered',
        'mark-failed' => 'Marked failed', 'sync' => 'Synced', 'send' => 'Sent',
        'refresh' => 'Refreshed', 'variants' => 'Changed sizes and colours of',
        'refund' => 'Refunded',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe() || $response->getStatusCode() >= 400) {
            return $response;
        }

        try {
            $this->record($request);
        } catch (Throwable $e) {
            Log::warning('Audit log entry not written.', ['route' => $request->route()?->getName(), 'error' => $e->getMessage()]);
        }

        return $response;
    }

    private function record(Request $request): void
    {
        $admin = $request->user('admin');
        $route = (string) $request->route()?->getName();

        if (! $admin instanceof AdminUser || $route === '' || Str::endsWith($route, self::SKIP)) {
            return;
        }

        AdminAuditEntry::query()->create([
            'admin_user_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_role' => AdminCapability::roleLabel($admin->role),
            'action' => self::label($route),
            'route' => $route,
            'target' => $this->target($request),
            'detail' => $this->detail($request),
            'ip' => $request->ip(),
        ]);
    }

    /** `api.v1.admin.workshop-sessions.update` → "Updated workshop session". */
    public static function label(string $route): string
    {
        $parts = explode('.', Str::after($route, 'admin.'));
        $last = array_pop($parts);
        $verb = self::VERBS[$last] ?? null;
        if ($verb === null) {
            // Not one of the usual verbs: name the thing and its action.
            $parts[] = $last;
            $verb = 'Changed';
        }
        // "settings.notifications.update" is about notifications.
        if (($parts[0] ?? null) === 'settings' && count($parts) > 1) {
            array_shift($parts);
        }
        $thing = Str::singular(str_replace('-', ' ', implode(' ', $parts)));
        $thing = $thing === 'me' ? 'their own account' : strtr($thing, ['fx' => 'exchange rate', 'diy' => 'DIY']);

        return trim("{$verb} {$thing}");
    }

    private function target(Request $request): ?string
    {
        foreach ($request->route()?->parameters() ?? [] as $value) {
            if ($value instanceof GiftCard) {
                return 'Card ending '.$value->lastFour();
            }
            if ($value instanceof Model) {
                foreach (['reference', 'name', 'title', 'email', 'slug', 'key'] as $field) {
                    if (filled($value->getAttribute($field))) {
                        return (string) $value->getAttribute($field);
                    }
                }

                return class_basename($value).' #'.$value->getKey();
            }
            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function detail(Request $request): ?string
    {
        $fields = array_values(array_diff(array_keys($request->except(self::SECRET)), self::SECRET));
        if ($request->allFiles()) {
            $fields[] = 'file';
        }
        if ($fields === []) {
            return null;
        }

        $detail = 'Fields: '.implode(', ', array_unique($fields));
        if (is_string($status = $request->input('status'))) {
            $detail .= ". Status: {$status}";
        }

        return Str::limit($detail, 500);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\AdminUser;
use App\Support\AdminCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin account management — **Super Admin only** for anything that writes.
 *
 * The README requires role tiers but never says who creates an account.
 * Without this the role system is unusable after handover: the seeded account
 * is the only one that can ever exist, and there is no way to give a new staff
 * member access without a developer and a database console.
 *
 * Everyone may read the list (§18 gives no tier a reason to be surprised by who
 * else is on the team); only the Super Admin may change it.
 */
class TeamController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AdminUserResource::collection(
            AdminUser::query()->orderBy('name')->get()
        );
    }

    public function store(StoreAdminUserRequest $request): AdminUserResource
    {
        return new AdminUserResource(AdminUser::create($request->validated()));
    }

    public function update(StoreAdminUserRequest $request, AdminUser $adminUser): JsonResponse|AdminUserResource
    {
        $data = $request->validated();

        // A blank password field means "leave it alone", not "set it to empty".
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        // Changing your own role would let a Super Admin demote themselves out
        // of the only tier that can undo it.
        if (
            $adminUser->id === $request->user('admin')->id
            && array_key_exists('role', $data)
            && $data['role'] !== $adminUser->role
        ) {
            return response()->json([
                'message' => 'You cannot change your own role. Ask another Super Admin to do it.',
            ], 422);
        }

        if ($this->wouldOrphanTeamManagement($adminUser, $data['role'] ?? $adminUser->role)) {
            return response()->json([
                'message' => 'This is the only Super Admin account. Promote someone else before changing it.',
            ], 422);
        }

        // Extending an intern's access is an audit event, not a field edit:
        // the dashboard shows who granted the extension and by how long, and
        // that history is only recoverable if it is written down as it happens.
        if (array_key_exists('access_expires_at', $data)) {
            $data['access_extensions'] = $this->recordExtension(
                $adminUser,
                $data['access_expires_at'],
                $request->user('admin'),
            );
        }

        $adminUser->update($data);

        return new AdminUserResource($adminUser->fresh());
    }

    public function destroy(Request $request, AdminUser $adminUser): JsonResponse
    {
        if ($adminUser->id === $request->user('admin')->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        // The one guard that matters: deleting the last account that can
        // manage the team leaves a system nobody can administer, and no number
        // of Admin or Staff accounts can undo it.
        if ($this->wouldOrphanTeamManagement($adminUser, null)) {
            return response()->json([
                'message' => 'This is the only Super Admin account. Promote someone else before deleting it.',
            ], 422);
        }

        $adminUser->delete();

        return response()->json(null, 204);
    }

    /**
     * True when this change would leave nobody holding `team.manage`.
     *
     * @param  string|null  $newRole  The role after the change; null for a deletion.
     */
    private function wouldOrphanTeamManagement(AdminUser $adminUser, ?string $newRole): bool
    {
        if (! AdminCapability::allows($adminUser->role, 'team.manage')) {
            return false;
        }

        if ($newRole !== null && AdminCapability::allows($newRole, 'team.manage')) {
            return false;
        }

        $managers = AdminUser::query()
            ->whereIn('role', array_filter(
                AdminCapability::ROLES,
                fn (string $role) => AdminCapability::allows($role, 'team.manage'),
            ))
            ->count();

        return $managers <= 1;
    }

    /** @return array<int, array<string, mixed>> */
    private function recordExtension(AdminUser $adminUser, ?string $newExpiry, AdminUser $actor): array
    {
        $previous = $adminUser->access_expires_at;

        if ((string) $previous === (string) $newExpiry) {
            return $adminUser->access_extensions ?? [];
        }

        return array_merge([[
            'extended_at' => now()->toIso8601String(),
            'extended_by_name' => $actor->name,
            'previous_expiry' => $previous?->toIso8601String(),
            'new_expiry' => $newExpiry,
            'days' => $newExpiry && $previous ? $previous->diffInDays($newExpiry, false) : null,
        ]], $adminUser->access_extensions ?? []);
    }
}

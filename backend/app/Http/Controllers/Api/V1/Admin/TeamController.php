<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin/Staff account management — **Admin only**.
 *
 * The README requires two-tier roles but never says who creates a Staff
 * account. Without this the role system is unusable after handover: the seeded
 * admin is the only account that can ever exist, and there is no way to give a
 * new staff member access without a developer and a database console.
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

        // Demoting yourself would leave you unable to undo it, and if you are
        // the last admin it locks the whole role out of the system.
        if (
            $adminUser->id === $request->user('admin')->id
            && ($data['role'] ?? $adminUser->role) !== 'admin'
        ) {
            return response()->json([
                'message' => 'You cannot change your own role. Ask another admin to do it.',
            ], 422);
        }

        $adminUser->update($data);

        return new AdminUserResource($adminUser->fresh());
    }

    public function destroy(Request $request, AdminUser $adminUser): JsonResponse
    {
        if ($adminUser->id === $request->user('admin')->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        // The one guard that matters: deleting the last admin leaves a system
        // nobody can administer, and no amount of Staff accounts can undo it.
        if ($adminUser->role === 'admin' && AdminUser::query()->where('role', 'admin')->count() === 1) {
            return response()->json([
                'message' => 'This is the only admin account. Promote someone else before deleting it.',
            ], 422);
        }

        $adminUser->delete();

        return response()->json(null, 204);
    }
}

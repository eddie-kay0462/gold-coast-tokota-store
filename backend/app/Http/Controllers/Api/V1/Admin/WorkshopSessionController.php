<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWorkshopSessionRequest;
use App\Http\Resources\Admin\AdminWorkshopSessionResource;
use App\Models\WorkshopSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Workshop session capacity management (README Feature 9 — Admin and Staff).
 */
class WorkshopSessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sessions = WorkshopSession::query()
            // Admin needs past sessions too — the storefront list hides them,
            // but "who came last month" is an admin question.
            ->when($request->boolean('upcoming'), fn ($q) => $q->whereDate('scheduled_date', '>=', today()))
            ->orderBy('scheduled_date')
            ->paginate(50)
            ->withQueryString();

        return AdminWorkshopSessionResource::collection($sessions);
    }

    public function store(StoreWorkshopSessionRequest $request): AdminWorkshopSessionResource
    {
        $session = WorkshopSession::create([
            ...$request->validated(),
            'created_by_admin_id' => $request->user('admin')->id,
        ]);

        return new AdminWorkshopSessionResource($session);
    }

    public function update(StoreWorkshopSessionRequest $request, WorkshopSession $workshopSession): JsonResponse|AdminWorkshopSessionResource
    {
        $capacity = $request->validated('capacity');

        // Cutting capacity below the seats already taken would silently put the
        // session into a state where remaining_capacity is negative and someone
        // turns up to no seat. Refuse and say who is affected.
        if ($capacity !== null && $capacity < $workshopSession->occupied_seats) {
            return response()->json([
                'message' => "This session already has {$workshopSession->occupied_seats} bookings. Cancel some before reducing capacity below that.",
            ], 422);
        }

        $workshopSession->update($request->validated());

        return new AdminWorkshopSessionResource($workshopSession->fresh());
    }

    public function destroy(WorkshopSession $workshopSession): JsonResponse|Response
    {
        // Deleting a session with people booked onto it loses their bookings
        // without telling them. Cancel the bookings first, deliberately.
        if ($workshopSession->occupied_seats > 0) {
            return response()->json([
                'message' => "This session has {$workshopSession->occupied_seats} bookings. Cancel them before deleting it.",
            ], 422);
        }

        $workshopSession->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWorkshopTypeRequest;
use App\Http\Resources\WorkshopTypeResource;
use App\Models\WorkshopType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The published workshop programme (§15), as the admin Workshops screen needs
 * it: every experience, active or not, with a count of what is scheduled.
 *
 * The six in §15 are seeded. The client can add more (a new event, a one-off
 * experience) and edit them from the admin, which is the instruction §22.3
 * asks for before the programme changes. Nothing here changes on its own. A
 * themed run of an existing experience is a session with its own title
 * (`WorkshopSessionController`), not a new type.
 */
class WorkshopTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return WorkshopTypeResource::collection(
            WorkshopType::query()
                ->withCount(['sessions' => fn ($query) => $query->upcoming()])
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function store(StoreWorkshopTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = WorkshopType::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'requires_appointment' => $data['requires_appointment'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => (int) WorkshopType::query()->max('sort_order') + 1,
        ]);

        return (new WorkshopTypeResource($type->loadCount(['sessions' => fn ($q) => $q->upcoming()])))
            ->response()->setStatusCode(201);
    }

    /** The slug stays put on rename: it's in links customers may have saved. */
    public function update(StoreWorkshopTypeRequest $request, WorkshopType $workshopType): WorkshopTypeResource
    {
        $workshopType->update($request->validated());

        return new WorkshopTypeResource($workshopType->loadCount(['sessions' => fn ($q) => $q->upcoming()]));
    }

    /** Only one that never had a session: anything else keeps its bookings' history, so it's switched off instead. */
    public function destroy(WorkshopType $workshopType): Response|JsonResponse
    {
        if ($workshopType->sessions()->exists()) {
            return response()->json([
                'message' => 'This experience has sessions, and their bookings need it. Switch it off instead, so it leaves the booking page.',
            ], 422);
        }

        $workshopType->delete();

        return response()->noContent();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'experience';
        $slug = $base;
        for ($n = 2; WorkshopType::query()->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}

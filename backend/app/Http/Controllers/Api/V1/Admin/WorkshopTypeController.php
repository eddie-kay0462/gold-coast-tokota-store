<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkshopTypeResource;
use App\Models\WorkshopType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The published workshop programme (§15), as the admin Workshops screen needs
 * it: every experience, active or not, with a count of what is scheduled.
 *
 * Read-only. The programme's days, times and capacities are published
 * commitments — §22.3 forbids changing them without instruction — so the thing
 * that changes day to day is a *session* scheduled against a type, which is
 * what `WorkshopSessionController` writes.
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
}

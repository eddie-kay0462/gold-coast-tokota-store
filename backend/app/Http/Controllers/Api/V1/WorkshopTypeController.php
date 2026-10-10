<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkshopTypeResource;
use App\Models\WorkshopType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The workshop programme as the storefront's booking page needs it (§15).
 *
 * Separate from `GET /workshop-sessions`, which lists bookable dates. Three of
 * the six experiences run only by appointment and so never appear in that
 * list — without this endpoint half the published programme is invisible to
 * customers.
 *
 * Inactive types are withheld: an experience the brand has turned off should
 * not be advertised, and the storefront has no use for one it cannot book.
 */
class WorkshopTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return WorkshopTypeResource::collection(
            WorkshopType::query()->active()->orderBy('sort_order')->get()
        );
    }
}

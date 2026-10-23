<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\CatalogueFacets;
use Illuminate\Http\JsonResponse;

/** The shop sidebar's filter options, from the catalogue (CatalogueFacets). */
class ProductFacetController extends Controller
{
    public function index(CatalogueFacets $facets): JsonResponse
    {
        return response()->json(['data' => $facets->all()]);
    }
}

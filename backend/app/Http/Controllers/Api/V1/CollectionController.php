<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CollectionResource::collection(Collection::query()->orderBy('name')->get());
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkshopSessionResource;
use App\Models\WorkshopSession;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkshopSessionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $sessions = WorkshopSession::query()
            ->upcoming()
            ->orderBy('scheduled_date')
            ->get();

        return WorkshopSessionResource::collection($sessions);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class SiteSettingController extends Controller
{
    /**
     * Laravel's JsonResource answers 201 when its model `wasRecentlyCreated`,
     * and SiteSetting::current() lazily creates the single settings row on
     * first access — so the very first GET after a fresh deploy answered
     * 201 Created for a read. Harmless-looking, and exactly the kind of thing
     * a cache or a client treats differently from a 200. Set explicitly here.
     */
    public function show(): JsonResponse
    {
        return (new SiteSettingResource(SiteSetting::current()))
            ->response()
            ->setStatusCode(200);
    }
}

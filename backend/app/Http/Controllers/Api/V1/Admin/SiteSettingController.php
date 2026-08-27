<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteSettingRequest;
use App\Http\Resources\SiteSettingResource;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

/**
 * Site Settings. **Writing is Admin-only** — the README's two-tier rule names
 * it explicitly alongside pricing and refunds. Reading is open to Staff, since
 * the WhatsApp number and contact details show on screens they work in.
 *
 * Single-row resource, so there is no id in the route: `SiteSetting::current()`
 * creates the row on first access.
 *
 * This is the endpoint behind the Feature 9 acceptance criterion that the
 * business owner can change the WhatsApp number and see it live on the
 * storefront without a deploy.
 */
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

    public function update(UpdateSiteSettingRequest $request): JsonResponse
    {
        $settings = SiteSetting::current();
        $settings->update($request->validated());

        return (new SiteSettingResource($settings->fresh()))
            ->response()
            ->setStatusCode(200);
    }
}

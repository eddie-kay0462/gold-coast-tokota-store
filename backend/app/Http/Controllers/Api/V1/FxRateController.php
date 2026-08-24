<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FxRateResource;
use App\Services\Currency\FxRateService;
use Illuminate\Http\JsonResponse;

class FxRateController extends Controller
{
    public function show(FxRateService $fxRateService): FxRateResource|JsonResponse
    {
        $fxRate = $fxRateService->getCachedRate();

        if (! $fxRate) {
            return response()->json([
                'data' => null,
                'meta' => null,
                'errors' => ['message' => 'No FX rate has been fetched yet.'],
            ], 503);
        }

        return new FxRateResource($fxRate);
    }
}

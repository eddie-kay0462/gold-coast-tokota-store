<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\MegaMenus;
use Illuminate\Http\JsonResponse;

/** The storefront header's mega menus, built from the catalogue (MegaMenus). */
class NavigationController extends Controller
{
    public function menus(MegaMenus $menus): JsonResponse
    {
        return response()->json(['data' => $menus->all()]);
    }
}

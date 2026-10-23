<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMegaMenuRequest;
use App\Models\MegaMenuPromo;
use App\Support\MegaMenus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Content › Menus: the two image tiles in each storefront mega menu. The
 * menus' links are built from the catalogue and aren't edited here.
 */
class MegaMenuController extends Controller
{
    public function index(MegaMenus $menus): JsonResponse
    {
        $chosen = MegaMenuPromo::query()->with('mediaAsset')->get()->groupBy('menu');
        $live = collect($menus->all())->keyBy('key');

        $data = collect(MegaMenus::MENUS)->map(function (array $menu, string $key) use ($chosen, $live, $menus) {
            $slots = $chosen->get($key, collect())->keyBy('position');

            return [
                'key' => $key,
                'label' => $menu['label'],
                'to' => $menu['to'],
                'slots' => collect(range(1, MegaMenus::SLOTS))->map(function (int $position) use ($slots) {
                    $promo = $slots->get($position);
                    $asset = $promo?->mediaAsset;

                    return [
                        'position' => $position,
                        'media_asset' => $asset ? [
                            'id' => $asset->id,
                            'url' => $asset->url,
                            'filename' => $asset->filename,
                            'alt_text' => $asset->alt_text,
                        ] : null,
                        'caption' => $promo?->caption ?? '',
                        'link' => $promo?->link ?? '',
                    ];
                })->all(),
                // What the storefront shows right now, empty slots filled in.
                'showing' => $live->get($key)['promos'] ?? [],
                'columns' => $live->get($key)['columns'] ?? [],
                'link_options' => $menus->links($key),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function update(UpdateMegaMenuRequest $request, string $menu): JsonResponse
    {
        abort_unless(array_key_exists($menu, MegaMenus::MENUS), 404);

        DB::transaction(function () use ($request, $menu) {
            foreach ($request->validated('slots') as $slot) {
                if (empty($slot['media_asset_id'])) {
                    MegaMenuPromo::query()->where('menu', $menu)->where('position', $slot['position'])->get()->each->delete();

                    continue;
                }

                MegaMenuPromo::query()->updateOrCreate(
                    ['menu' => $menu, 'position' => $slot['position']],
                    ['media_asset_id' => $slot['media_asset_id'], 'caption' => trim($slot['caption']), 'link' => $slot['link']],
                );
            }
        });

        MegaMenus::forget();

        return $this->index(app(MegaMenus::class));
    }
}

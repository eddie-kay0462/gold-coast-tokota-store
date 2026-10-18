<?php

namespace App\Http\Requests\Admin;

use App\Services\Shipping\PointInPolygon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The Accra service polygon and the free-shipping threshold.
 *
 * The polygon is checked for shape, not just for being JSON: a polygon that
 * parses but has no rings would quietly turn the Accra zone off, and one in
 * [lat, lng] order would put Accra in the Gulf of Guinea.
 */
class UpdateShippingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Omitted means "leave as it is", so a screen saving only the
            // threshold never has to send the polygon back.
            'accra_polygon' => ['sometimes', 'nullable', 'array'],
            'free_shipping_threshold_ghs' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $geojson = $this->input('accra_polygon');

            if (! is_array($geojson) || $validator->errors()->has('accra_polygon')) {
                return;
            }

            $problem = $this->polygonProblem($geojson);

            if ($problem !== null) {
                $validator->errors()->add('accra_polygon', $problem);
            }
        });
    }

    /** @param  array<string, mixed>  $geojson */
    private function polygonProblem(array $geojson): ?string
    {
        $polygons = PointInPolygon::polygons($geojson);

        if ($polygons === []) {
            return 'Upload a GeoJSON Polygon or MultiPolygon (a Feature or FeatureCollection holding one is fine).';
        }

        foreach ($polygons as $rings) {
            if (! is_array($rings) || $rings === []) {
                return 'Every polygon needs an outline.';
            }

            foreach ($rings as $ring) {
                if (! is_array($ring) || count($ring) < 4) {
                    return 'Every ring needs at least four points, the last repeating the first.';
                }

                foreach ($ring as $point) {
                    if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
                        return 'Every point must be a [longitude, latitude] pair of numbers.';
                    }

                    // Ghana sits between roughly 4.5°N–11.2°N and 3.3°W–1.2°E.
                    // A point outside that box is almost always lat/lng swapped.
                    if ($point[0] < -3.5 || $point[0] > 1.5 || $point[1] < 4.5 || $point[1] > 11.5) {
                        return 'A point falls outside Ghana. GeoJSON wants [longitude, latitude]; check the pairs are not swapped.';
                    }
                }
            }
        }

        return null;
    }
}

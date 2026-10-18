<?php

namespace App\Services\Shipping;

/**
 * Is a point inside a GeoJSON Polygon or MultiPolygon?
 *
 * Even-odd ray casting over each ring. The first ring of a polygon is its
 * outline and any further rings are holes, as GeoJSON defines them. A point
 * exactly on an edge counts as inside: someone standing on the boundary of the
 * Accra service area is better off quoted by Yango than flat-rated.
 *
 * Coordinates are GeoJSON order, [lng, lat]. Planar maths is fine at city
 * scale; this is not meant for polygons that cross the antimeridian.
 */
final class PointInPolygon
{
    /** @param  array<string, mixed>  $geometry  A GeoJSON geometry, Feature or FeatureCollection. */
    public static function contains(array $geometry, float $lat, float $lng): bool
    {
        foreach (self::polygons($geometry) as $rings) {
            if (self::inPolygon($rings, $lng, $lat)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flattens whatever GeoJSON was uploaded into a list of polygons, each a
     * list of rings.
     *
     * @param  array<string, mixed>  $geojson
     * @return list<list<list<array{0: float, 1: float}>>>
     */
    public static function polygons(array $geojson): array
    {
        return match ($geojson['type'] ?? null) {
            'Polygon' => [$geojson['coordinates'] ?? []],
            'MultiPolygon' => array_values($geojson['coordinates'] ?? []),
            'Feature' => self::polygons($geojson['geometry'] ?? []),
            'FeatureCollection' => array_merge(...array_map(
                fn (array $feature) => self::polygons($feature),
                [[], ...($geojson['features'] ?? [])],
            )),
            default => [],
        };
    }

    /** @param  list<list<array{0: float, 1: float}>>  $rings */
    private static function inPolygon(array $rings, float $x, float $y): bool
    {
        if ($rings === [] || ! self::inRing($rings[0], $x, $y)) {
            return false;
        }

        foreach (array_slice($rings, 1) as $hole) {
            if (self::inRing($hole, $x, $y) && ! self::onBoundary($hole, $x, $y)) {
                return false;
            }
        }

        return true;
    }

    /** @param  list<array{0: float, 1: float}>  $ring */
    private static function inRing(array $ring, float $x, float $y): bool
    {
        if (self::onBoundary($ring, $x, $y)) {
            return true;
        }

        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = [(float) $ring[$i][0], (float) $ring[$i][1]];
            [$xj, $yj] = [(float) $ring[$j][0], (float) $ring[$j][1]];

            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** @param  list<array{0: float, 1: float}>  $ring */
    private static function onBoundary(array $ring, float $x, float $y): bool
    {
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = [(float) $ring[$i][0], (float) $ring[$i][1]];
            [$xj, $yj] = [(float) $ring[$j][0], (float) $ring[$j][1]];

            $cross = ($x - $xi) * ($yj - $yi) - ($y - $yi) * ($xj - $xi);

            if (abs($cross) < 1e-12
                && $x >= min($xi, $xj) && $x <= max($xi, $xj)
                && $y >= min($yi, $yj) && $y <= max($yi, $yj)) {
                return true;
            }
        }

        return false;
    }
}

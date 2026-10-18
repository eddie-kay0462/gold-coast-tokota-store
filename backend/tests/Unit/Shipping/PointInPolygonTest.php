<?php

namespace Tests\Unit\Shipping;

use App\Services\Shipping\PointInPolygon;
use PHPUnit\Framework\TestCase;

class PointInPolygonTest extends TestCase
{
    /** A box over central Accra, [lng, lat] as GeoJSON has it. */
    private const ACCRA = [
        'type' => 'Polygon',
        'coordinates' => [[[-0.30, 5.50], [-0.10, 5.50], [-0.10, 5.70], [-0.30, 5.70], [-0.30, 5.50]]],
    ];

    public function test_a_point_inside_is_contained(): void
    {
        // Osu.
        $this->assertTrue(PointInPolygon::contains(self::ACCRA, 5.556, -0.18));
    }

    public function test_a_point_outside_is_not(): void
    {
        // Kumasi.
        $this->assertFalse(PointInPolygon::contains(self::ACCRA, 6.69, -1.62));
    }

    /** Swapped lat/lng is the classic GeoJSON mistake; it must not match. */
    public function test_latitude_and_longitude_are_not_interchangeable(): void
    {
        $this->assertFalse(PointInPolygon::contains(self::ACCRA, -0.18, 5.556));
    }

    public function test_a_point_on_the_edge_counts_as_inside(): void
    {
        $this->assertTrue(PointInPolygon::contains(self::ACCRA, 5.50, -0.20));
        $this->assertTrue(PointInPolygon::contains(self::ACCRA, 5.70, -0.30));
    }

    public function test_a_point_in_a_hole_is_outside(): void
    {
        $withHole = self::ACCRA;
        $withHole['coordinates'][] = [[-0.22, 5.58], [-0.18, 5.58], [-0.18, 5.62], [-0.22, 5.62], [-0.22, 5.58]];

        $this->assertFalse(PointInPolygon::contains($withHole, 5.60, -0.20));
        $this->assertTrue(PointInPolygon::contains($withHole, 5.52, -0.12));
    }

    public function test_multipolygons_and_features_are_understood(): void
    {
        $tema = [[[0.00, 5.60], [0.05, 5.60], [0.05, 5.68], [0.00, 5.68], [0.00, 5.60]]];

        $multi = ['type' => 'MultiPolygon', 'coordinates' => [self::ACCRA['coordinates'], $tema]];
        $collection = ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => [], 'geometry' => $multi],
        ]];

        $this->assertTrue(PointInPolygon::contains($multi, 5.64, 0.02));
        $this->assertTrue(PointInPolygon::contains($collection, 5.556, -0.18));
        $this->assertFalse(PointInPolygon::contains($collection, 6.69, -1.62));
    }

    public function test_anything_that_is_not_a_polygon_contains_nothing(): void
    {
        $this->assertFalse(PointInPolygon::contains(['type' => 'Point', 'coordinates' => [-0.18, 5.556]], 5.556, -0.18));
        $this->assertFalse(PointInPolygon::contains([], 5.556, -0.18));
    }
}

<?php

namespace Database\Seeders;

use App\Models\ShippingZoneRate;
use Illuminate\Database\Seeder;

/**
 * One row per Ghana region, inactive and unpriced.
 *
 * No rate is invented here: the brand document states none, and §22 forbids
 * making one up. A region is offered at checkout only once an Admin has set
 * its price and switched it on. Safe to re-run — existing rows are left as
 * they are, so seeding never overwrites a price someone has set.
 */
class ShippingZoneRateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ShippingZoneRate::REGIONS as $region) {
            ShippingZoneRate::query()->firstOrCreate(
                ['region' => $region],
                ['amount_ghs' => 0, 'is_active' => false],
            );
        }
    }
}

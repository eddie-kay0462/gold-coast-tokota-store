<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Creates the CMS page rows the storefront links to, so the owner can edit them
 * from admin rather than waiting on a developer.
 *
 * Slugs are FLAT — `privacy`, not `legal/privacy`. The `/legal` and `/help` URL
 * prefixes are storefront information architecture; `GET /api/v1/pages/{slug}`
 * takes a bare slug, and a flat list is what the admin CMS should present.
 *
 * Every row is seeded with a null body and `is_draft = true`, which is what
 * makes this safe: the storefront falls back to its own placeholder copy and
 * shows the "draft — awaiting review" banner until someone writes and approves
 * the real text. Seeding placeholder legal copy into `body` here would suppress
 * that banner and publish unreviewed policy text.
 */
class PageSeeder extends Seeder
{
    /**
     * Titles only. Bodies are written in admin.
     *
     * @var array<string, string>
     */
    private const PAGES = [
        'about' => 'About Gold Coast Tokota',
        'privacy' => 'Privacy Policy',
        'terms' => 'Terms of Service',
        'do-not-sell' => 'Do Not Sell or Share My Personal Information',
        'supply-chain' => 'Supply Chain Transparency',
        'vendor-code' => 'Vendor Code of Conduct',
        'returns' => 'Returns & Exchanges',
        'shipping' => 'Shipping & Delivery',
        'bulk-orders' => 'Bulk & Corporate Orders',
        'accessibility' => 'Accessibility',
    ];

    public function run(): void
    {
        foreach (self::PAGES as $slug => $title) {
            // `about` already has live copy on the storefront, so it is seeded
            // with a body and is not a draft. Everything else waits for the owner.
            $isAbout = $slug === 'about';

            Page::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'body' => $isAbout ? '<p>Handmade in Ghana, one pair at a time.</p>' : null,
                    'is_draft' => ! $isAbout,
                ],
            );
        }
    }
}

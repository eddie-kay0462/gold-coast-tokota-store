<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Collection;
use App\Models\FxRate;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! AdminUser::query()->where('email', 'admin@goldcoasttokota.store')->exists()) {
            AdminUser::factory()->create([
                'name' => 'Test Admin',
                'email' => 'admin@goldcoasttokota.store',
                'role' => 'admin',
            ]);
        }

        SiteSetting::current()->update([
            'whatsapp_number' => '233200000000',
            'whatsapp_default_message' => 'Hi! I have a question about your sandals.',
            'contact_email' => 'hello@goldcoasttokota.store',
            'diy_turnaround_estimate' => '2-3 weeks',
            // Per-order-type estimates for the admin Workshops screen. These
            // are the brand's to rewrite; seeded so a fresh database matches
            // what the screen was designed against rather than showing nothing.
            'diy_turnaround_tiers' => [
                ['id' => 'standard', 'label' => 'Standard sandal order', 'estimate' => '1-2 business days', 'sort_order' => 1],
                ['id' => 'custom', 'label' => 'Custom sandal order', 'estimate' => '3-5 business days', 'sort_order' => 2],
                ['id' => 'kit', 'label' => 'DIY sandal kit', 'estimate' => '1-2 business days', 'sort_order' => 3],
                ['id' => 'bulk', 'label' => 'Bulk orders (20+ pairs)', 'estimate' => '1-3 weeks (depending on quantity)', 'sort_order' => 4],
                ['id' => 'corporate', 'label' => 'Corporate & event orders', 'estimate' => '1-2 weeks (subject to project scope)', 'sort_order' => 5],
            ],
            // Deliberately conservative. The approved mockup's bar reads
            // "Free delivery in Accra" and "Order online, pick up in Osu";
            // neither is confirmed — checkout charges for Accra delivery, and
            // the brand's address is Haatso, not Osu. Seed only what the rest
            // of the project can stand behind and let the brand edit the rest
            // from admin. See FOR_THE_TEAM.md open decisions.
            'announcements' => [
                'Handcrafted in Ghana',
                'Pay with MoMo or card',
                'We ship worldwide',
            ],
        ]);

        // All page seeding lives in PageSeeder so the CMS slugs are in one place.
        $this->call(PageSeeder::class);

        // Bootstrap rate so price_usd resolves on the very first request,
        // before RefreshFxRate has ever run (Feature 2). A placeholder
        // source makes it obvious in admin/logs that this isn't a live fetch.
        if (! FxRate::query()->exists()) {
            FxRate::factory()->create([
                'rate' => 0.075,
                'source' => 'seed-placeholder',
            ]);
        }

        // Categories are the top-nav split; collections are the merchandising
        // grouping within one (see design_prototype_schema_gaps memory / README
        // deviation). Names match the colleague's design prototype so seeded
        // data lines up with what the storefront actually renders.
        $sandals = Category::query()->firstOrCreate(['slug' => 'sandals'], ['name' => 'Sandals']);
        $ahenema = Category::query()->firstOrCreate(['slug' => 'ahenema'], ['name' => 'Ahenema']);

        $sikapa = Collection::query()->firstOrCreate(['slug' => 'sikapa'], ['name' => 'Sikapa']);
        $obrempong = Collection::query()->firstOrCreate(['slug' => 'obrempong'], ['name' => 'Obrempong']);
        $slides = Collection::query()->firstOrCreate(['slug' => 'slides'], ['name' => 'Slides']);

        // The six products the storefront was actually designed around, with
        // their real copy, photography and per-size stock. Runs first so a
        // fresh database looks like the approved mockup rather than like faker.
        $this->call(ProductSeeder::class);

        // A handful of faker products on top, so pagination, the listing
        // filters and the empty states have more than six rows to work with.
        if (Product::query()->count() <= 6) {
            Product::factory()
                ->count(3)
                ->featured()
                ->has(InventoryItem::factory()->count(2))
                ->create(['category_id' => $ahenema->id, 'collection_id' => $obrempong->id]);

            Product::factory()
                ->count(2)
                ->onSale()
                ->has(InventoryItem::factory()->count(2))
                ->create(['category_id' => $sandals->id, 'collection_id' => $sikapa->id]);

            Product::factory()
                ->count(2)
                ->has(InventoryItem::factory()->count(2))
                ->create(['category_id' => $sandals->id, 'collection_id' => $slides->id]);
        }

        // Titles match the colleague's design prototype (Stories section)
        // so /blog has real, on-brand content once the frontend wires it up.
        $posts = [
            ['title' => 'The story of the ahenema', 'slug' => 'the-story-of-the-ahenema'],
            ['title' => 'Inside the Accra workshop', 'slug' => 'inside-the-accra-workshop'],
            ['title' => 'Upcycled by design', 'slug' => 'upcycled-by-design'],
        ];

        foreach ($posts as $post) {
            BlogPost::query()->firstOrCreate(
                ['slug' => $post['slug']],
                [
                    'title' => $post['title'],
                    'body' => '<p>Handmade in Ghana, one pair at a time.</p>',
                    'author' => 'Gold Coast Tokota',
                    'published_at' => now(),
                    'is_published' => true,
                ],
            );
        }
    }
}

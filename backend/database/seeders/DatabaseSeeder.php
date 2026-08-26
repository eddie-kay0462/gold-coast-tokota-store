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

        if (Product::query()->count() === 0) {
            Product::factory()
                ->count(3)
                ->featured()
                ->has(InventoryItem::factory()->count(2))
                ->create(['category_id' => $ahenema->id, 'collection_id' => $obrempong->id]);

            Product::factory()
                ->count(2)
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

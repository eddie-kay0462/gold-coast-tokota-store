<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\BlogPost;
use App\Models\FxRate;
use App\Models\SiteSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seeded as super_admin, not admin: §18 puts team management and
        // payment configuration outside the Admin tier, so an `admin` seed
        // would leave a fresh database with nobody able to create the first
        // real account.
        //
        // The five people §17 names are deliberately NOT seeded. The document
        // gives their roles and job titles but no email addresses, and
        // inventing credentials for real colleagues is not something a seeder
        // should do — see FOR_THE_TEAM.md.
        if (! AdminUser::query()->where('email', 'admin@goldcoasttokota.store')->exists()) {
            AdminUser::factory()->create([
                'name' => 'Test Super Admin',
                'job_title' => 'Founder & CEO',
                'email' => 'admin@goldcoasttokota.store',
                'role' => 'super_admin',
            ]);
        }

        SiteSetting::current()->update([
            'whatsapp_number' => '233200000000',
            // Deliberately still the placeholder. The brand document gives
            // +233 25 753 4297 but annotates it "update with official number",
            // so it stays unset until the business confirms — a wrong number
            // here silently breaks the main ordering channel. See
            // FOR_THE_TEAM.md issue #13.
            'whatsapp_default_message' => 'Hi Gold Coast Tokota, I\'d like to know more about your sandals.',
            // Verbatim from the brand guidelines, "Default Greeting Message".
            'whatsapp_greeting' => implode("\n", [
                'Welcome to Gold Coast Tokota!',
                '',
                'Thank you for contacting us. We create handcrafted sustainable footwear from recycled materials while celebrating Ghanaian culture through immersive experiences and craftsmanship.',
                '',
                'Whether you are looking to:',
                '- Shop our handcrafted sandals',
                '- Book a Sandal Sip & Paint experience',
                '- Schedule a school or group tour',
                '- Discuss partnerships or bulk orders',
                '- Learn more about our sustainability initiatives',
                '',
                'We are here to help.',
                '',
                'Our team typically responds during business hours:',
                'Monday - Saturday: 9:00 AM - 5:00 PM (GMT)',
                '',
                'Please let us know how we can assist you today.',
                '',
                'Gold Coast Tokota',
                'Crafted with Purpose. Inspired by Culture.',
            ]),
            'business_hours' => 'Mon–Sat · 9am–5pm GMT',
            'contact_email' => 'hello@goldcoasttokota.store',
            // §12. The document annotates the phone number "(update with the
            // official number)", so it is seeded as given and flagged in
            // admin rather than treated as final — see FOR_THE_TEAM.md.
            'contact_phone' => '+233 25 753 4297',
            'address' => 'Haatso, Accra, Ghana',
            // §24.
            'tagline' => 'Crafted with Purpose. Inspired by Culture.',
            // §16's "DIY Sandal Kit" row. Was '2-3 weeks', which contradicted
            // the published turnaround table outright — the storefront's DIY
            // order form quotes this string directly.
            'diy_turnaround_estimate' => '1–2 business days',
            // Per-order-type estimates for the admin Workshops screen. These
            // are the brand's to rewrite; seeded so a fresh database matches
            // what the screen was designed against rather than showing nothing.
            'diy_turnaround_tiers' => [
                ['id' => 'standard', 'label' => 'Standard sandal order', 'estimate' => '1–2 business days', 'sort_order' => 1],
                ['id' => 'custom', 'label' => 'Custom sandal order', 'estimate' => '3–5 business days', 'sort_order' => 2],
                ['id' => 'kit', 'label' => 'DIY sandal kit', 'estimate' => '1–2 business days', 'sort_order' => 3],
                ['id' => 'bulk', 'label' => 'Bulk orders (20+ pairs)', 'estimate' => '1–3 weeks (depending on quantity)', 'sort_order' => 4],
                ['id' => 'corporate', 'label' => 'Corporate & event orders', 'estimate' => '1–2 weeks (subject to project scope)', 'sort_order' => 5],
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

        // The six experiences §15 publishes, with their real days, times and
        // capacity ceilings.
        $this->call(WorkshopTypeSeeder::class);

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

        // The brand's real catalogue — 26 slipper styles and 2 shoes, from the
        // photo folders and price sheets the client supplied. Nothing is
        // padded with faker any more: 28 real products are enough to exercise
        // pagination and the listing filters, and a made-up product sitting
        // next to a real one is indistinguishable from it on the storefront.
        $this->call(ProductSeeder::class);

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

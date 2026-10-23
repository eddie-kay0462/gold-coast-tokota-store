<?php

namespace App\Support;

use App\Models\MediaAsset;
use App\Models\Page;

/**
 * The storefront's About page as editable content (Content › Pages › About).
 *
 * Stored on the `about` page row's `content` column, one key per section.
 * Whatever hasn't been saved comes from defaults() — today's copy, researched
 * from GOLD_COAST_TOKOTA.md, the brand's channels and press on 23 Oct 2026 —
 * so a fresh database, or a section added later, still renders in full.
 *
 * Keys are camelCase because the admin app camelises every response key; a
 * snake_case key would come back to the API renamed.
 *
 * Images: each image block has a default `image` (a static storefront path)
 * and an optional `imageId` (a media library asset). The public payload adds
 * `imageUrl`: the asset's URL when it still exists, the default otherwise.
 */
final class AboutPage
{
    /** Sections in page order, with the image blocks among them. */
    public const SECTIONS = ['hero', 'statement', 'story', 'figures', 'materials', 'people', 'ahenema', 'experiences', 'watch', 'slogans', 'recognition', 'mission', 'socials'];

    private const IMAGE_SECTIONS = ['hero', 'people', 'ahenema'];

    /** @return array<string, array<string, mixed>> */
    public static function defaults(): array
    {
        $springboard = 'https://www.springboard.com.gh/2026/05/25/african-brands-must-compete-globally-through-innovation-and-culture-says-gold-coast-tokota-founder/';
        $businessBeat = 'https://businessbeat24.com/gold-coast-tokota-eco-friendly-solutions-one-shoe-at-a-time/';

        return [
            'hero' => [
                'visible' => true,
                'eyebrow' => 'About Gold Coast Tokota',
                'heading' => "Crafted with purpose.\nInspired by culture.",
                'lede' => 'We turn discarded tyres, water sachets and old denim into handcrafted ahenema and sandals in Haatso, Accra, and invite people into the workshop to see how it’s done.',
                'image' => '/design/about-hero-ahotoso.webp',
                'imageId' => null,
                'imageAlt' => 'The Ahotoso ahenema: black leather with gold crowns, handmade by Gold Coast Tokota',
            ],
            'statement' => [
                'visible' => true,
                'text' => 'Handmade in Ghana, one pair at a time.',
            ],
            'story' => [
                'visible' => true,
                'eyebrow' => 'Our story',
                'heading' => 'It started with a pair of sandals that let him down.',
                'paragraphs' => [
                    'On the way to meet a mentor, our founder Samuel Kumi-Gyau’s sandals gave way, and he missed the meeting. In 2022 he decided Ghana deserved footwear that was made here and made to last, and set out to build the brand himself.',
                    'Before the first pair, he travelled outside Ghana to learn how customers abroad saw African footwear. In 2023 Gold Coast Tokota opened its workshop in Haatso, Accra, making ahenema, the sandal of Ghanaian royalty, and sandals for everyday wear, from leather, velvet and materials other people throw away.',
                    'Today the workshop is also a classroom. Schools, companies and visitors come to paint, stitch and see how waste becomes something worth wearing.',
                ],
                'quote' => 'We are changing the narrative about manufacturing in Africa.',
                'quoteBy' => 'Samuel Kumi-Gyau, Founder & CEO',
                'quoteSource' => 'Springboard Hangout, May 2026',
                'quoteUrl' => $springboard,
            ],
            'figures' => [
                'visible' => true,
                'items' => [
                    ['value' => '2,500+', 'label' => 'pairs made and sold across Africa, Europe and Asia', 'source' => 'Business Beat, Feb 2025', 'href' => $businessBeat],
                    ['value' => '5 tonnes', 'label' => 'of tyres and denim turned into footwear', 'source' => 'Business Beat, Feb 2025', 'href' => $businessBeat],
                    ['value' => '5–25%', 'label' => 'of every pair is reclaimed fabric or tyre', 'source' => 'Ghana Climate Innovation Centre', 'href' => 'https://ghanacic.ashesi.edu.gh/ventures/gold-coast-tokota/'],
                    ['value' => '20', 'label' => 'pairs a day, each one made by hand', 'source' => 'Springboard, May 2026', 'href' => $springboard],
                ],
            ],
            'materials' => [
                'visible' => true,
                'eyebrow' => 'What we make them from',
                'heading' => 'Waste isn’t waste until we waste it.',
                'body' => 'Tyres, water sachets and worn-out denim are thrown away across Ghana every day. We collect them and work them into footwear by hand, so less goes to landfill and less new leather goes into each pair.',
                'items' => [
                    ['name' => 'Car tyres', 'body' => 'Collected from FITA Autotech’s service stations before they reach a landfill, and cut into hard-wearing soles.'],
                    ['name' => 'Water sachets', 'body' => 'Ocean-bound plastic: the drinking-water sachets Ghana gets through every day, kept out of the gutters.'],
                    ['name' => 'Denim', 'body' => 'Old jeans and textile offcuts, given a second life in the pair instead of a dump.'],
                    ['name' => 'Leather and velvet', 'body' => 'Finished by hand into the crowns, straps and linings of each pair of ahenema.'],
                ],
            ],
            'people' => [
                'visible' => true,
                'eyebrow' => 'The people',
                'heading' => 'Made by hand, by artisans we train.',
                'body' => 'Our workshop trains and employs young people, women and people living with disabilities as skilled artisans. Every pair passes through their hands, from the first cut of the sole to the last stitch.',
                'image' => '/design/post-tyred-hero.png',
                'imageId' => null,
                'imageAlt' => 'The Gold Coast Tokota team in the workshop, holding their sandals, with two of the FITA Autotech team, under the brand’s Africa map',
                'caption' => 'The Gold Coast Tokota team with FITA Autotech, who supply the tyres.',
                'team' => [
                    ['role' => 'Founder & CEO', 'name' => 'Samuel Kumi-Gyau'],
                    ['role' => 'Operations Lead', 'name' => 'Mary Seade'],
                ],
            ],
            'ahenema' => [
                'visible' => true,
                'eyebrow' => 'The ahenema',
                'heading' => "Fit for royalty,\nmade for every day.",
                'body' => 'Ahenema have been worn by Ghanaian royalty for generations. We keep the shape, the crowns and the Adinkra symbols, and build them from reclaimed materials, leather and velvet. Now we’re making Orthopaedic Ahenema too, so the royal sandal can be worn all day.',
                'image' => '/design/about-ahenema-kentehene.webp',
                'imageId' => null,
                'imageAlt' => 'The Kentehene ahenema: a blue suede footbed with a woven kente strap and leather crown',
            ],
            'experiences' => [
                'visible' => true,
                'eyebrow' => 'Experiences',
                'heading' => 'Come and make a pair with us.',
                'body' => 'The workshop is open to everyone: paint your own sandals, spend a day at the bench, or bring a class to see how waste becomes footwear.',
                'items' => [
                    ['name' => 'Sandal Sip & Paint', 'when' => 'Every Saturday · 10:00 AM – 1:00 PM', 'detail' => 'Paint your own pair with Adinkra symbols. Up to 20 people.'],
                    ['name' => 'Be a Shoemaker for a Day', 'when' => 'Every Friday · 9:00 AM – 4:00 PM', 'detail' => 'A full day at the bench with our artisans. Up to 10 people.'],
                    ['name' => 'School Sustainability Tours', 'when' => 'Monday – Friday', 'detail' => 'How waste becomes footwear, for up to 40 students.'],
                    ['name' => 'Corporate Team Building', 'when' => 'By appointment', 'detail' => 'Half or full day, up to 30 people.'],
                ],
            ],
            'watch' => [
                'visible' => true,
                'eyebrow' => 'From the workshop',
                'heading' => 'See the work for yourself.',
                'body' => 'Pairs being made, classrooms on a visit, Sip & Paint afternoons, and the founder in conversation, from our Instagram and YouTube.',
                'posts' => [
                    ['platform' => 'youtube', 'id' => 'sPsziRAI9JE', 'title' => 'Trash to handcrafted ahenema', 'meta' => 'Gold Coast Tokota · YouTube Short', 'shape' => 'portrait', 'thumb' => 'maxresdefault'],
                    ['platform' => 'instagram', 'id' => 'DdmepfdOMlX', 'title' => 'Nubuke tries the tyre shoe', 'meta' => 'Nubuke × Gold Coast Tokota · Instagram', 'shape' => 'portrait', 'thumb' => null],
                    ['platform' => 'instagram', 'id' => 'DWlMVGmDcF-', 'title' => 'Edlys Montessori pupils visit the workshop', 'meta' => 'Gold Coast Tokota · Instagram', 'shape' => 'portrait', 'thumb' => null],
                    ['platform' => 'youtube', 'id' => 'lkWkuDK7a7s', 'title' => 'Paint your story. Wear your story.', 'meta' => 'Sandal Sip & Paint · YouTube Short', 'shape' => 'portrait', 'thumb' => 'oardefault'],
                    ['platform' => 'instagram', 'id' => 'DY1nm0oNGLa', 'title' => 'Africa Day Sip & Paint with MTN Ghana', 'meta' => 'Gold Coast Tokota · Instagram', 'shape' => 'portrait', 'thumb' => null],
                    ['platform' => 'youtube', 'id' => 'jotLR4hzqPE', 'title' => 'DIY: shoemaking with Gold Coast Tokota', 'meta' => 'Adom TV, Life & Living It · 7 min', 'shape' => 'landscape', 'thumb' => 'maxresdefault'],
                    ['platform' => 'instagram', 'id' => 'DUDYvAgjRUj', 'title' => 'Ahenema on a global stage, with Wode Maya', 'meta' => 'Gold Coast Tokota · Instagram', 'shape' => 'portrait', 'thumb' => null],
                    ['platform' => 'youtube', 'id' => 'H1z2YRpgDII', 'title' => 'From trash to treasure', 'meta' => 'Conrado TV · 36 min', 'shape' => 'landscape', 'thumb' => 'maxresdefault'],
                    ['platform' => 'youtube', 'id' => 'X1sbKOc_VTA', 'title' => 'Orange Corners Ghana: Samuel Kumi-Gyau', 'meta' => 'Joy Business · 1 min', 'shape' => 'landscape', 'thumb' => null],
                    ['platform' => 'youtube', 'id' => 'zPEedEZoNos', 'title' => 'Entrepreneurship & cultural heritage', 'meta' => 'Springboard Hangout, Joy Prime · 32 min', 'shape' => 'landscape', 'thumb' => null],
                    ['platform' => 'youtube', 'id' => 'PHqE6nU1xo0', 'title' => 'The GH₵2,000 decision that changed everything', 'meta' => 'The Next Africa · 67 min', 'shape' => 'landscape', 'thumb' => 'maxresdefault'],
                ],
            ],
            // Off since 23 Oct at the client's request; switch it back on in
            // Content › Pages › About.
            'slogans' => [
                'visible' => false,
                'items' => ['Crafted with Purpose', 'Inspired by Culture', 'Made in Ghana'],
            ],
            'recognition' => [
                'visible' => true,
                'eyebrow' => 'Recognition',
                'heading' => 'Backed by people who build businesses.',
                'items' => [
                    ['name' => 'Orange Corners Ghana', 'detail' => 'Cohort 9 of the acceleration programme of the Kingdom of the Netherlands, run by GrowthAfrica', 'date' => '', 'href' => 'https://www.youtube.com/watch?v=X1sbKOc_VTA'],
                    ['name' => 'Ghana Climate Innovation Centre', 'detail' => 'Cohort 10 of its green-economy incubator at Ashesi University', 'date' => 'Dec 2024', 'href' => 'https://ghanacic.ashesi.edu.gh/ventures/gold-coast-tokota/'],
                    ['name' => 'BlueInvest Africa', 'detail' => 'Blue Economy Program award', 'date' => 'Oct 2025', 'href' => 'https://magazine.thenextafrica.com/gold-coast-tokota-scoops-blue-economy-program-award/'],
                    ['name' => 'Africa Business Bureau', 'detail' => 'Third place, Anchor Incubator pitch day', 'date' => 'Dec 2025', 'href' => 'https://ghanaiantimes.com.gh/abb-hosts-successful-pitch-event-for-the-second-cohort-of-the-anchor-incubator-project/'],
                    ['name' => 'YEF Entrepreneurship Challenge', 'detail' => 'Sixth place, with GH₵10,000 of advertising support', 'date' => 'Apr 2026', 'href' => 'https://thebftonline.com/2026/04/18/gold-coast-tokota-to-leverage-gh%C2%A210000-yef-advertising-support-for-visibility-growth/'],
                ],
            ],
            'mission' => [
                'visible' => true,
                'heading' => 'Where we’re going.',
                'lede' => 'A future where African manufacturing creates value for people, culture and the planet.',
                'mission' => 'To reshape manufacturing in Africa by transforming waste into premium handcrafted footwear, preserving Ghanaian craftsmanship, empowering young people through skills development and creating sustainable cultural experiences that generate lasting social, environmental and economic impact.',
                'vision' => 'To become Africa’s leading sustainable footwear and cultural tourism enterprise, inspiring a future where African craftsmanship, circular manufacturing and cultural heritage drive inclusive economic growth and global recognition.',
            ],
            'socials' => [
                'instagram' => 'https://www.instagram.com/goldcoasttokota/',
                'youtube' => 'https://www.youtube.com/@GoldCoastTokota',
            ],
        ];
    }

    /**
     * Validation for a save. The editor sends the whole page, so every
     * section is required and validated whole. Text is plain: the storefront
     * prints it as text, never as HTML.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $text = fn (int $max) => ['required', 'string', 'max:'.$max];
        $optional = fn (int $max) => ['nullable', 'string', 'max:'.$max];
        $url = ['required', 'url:https,http', 'max:500'];
        $image = fn (string $s) => [
            "{$s}.imageId" => ['nullable', 'integer', 'exists:media_assets,id'],
            "{$s}.imageAlt" => $text(300),
        ];

        $rules = ['content' => ['required', 'array']];
        foreach (self::SECTIONS as $s) {
            $rules["content.{$s}"] = ['required', 'array'];
            if ($s !== 'socials') {
                $rules["content.{$s}.visible"] = ['required', 'boolean'];
            }
        }

        return $rules + self::prefix('content.', [
            'hero.eyebrow' => $optional(80), 'hero.heading' => $text(160), 'hero.lede' => $text(600),
            ...$image('hero'),
            'statement.text' => $text(400),
            'story.eyebrow' => $optional(80), 'story.heading' => $text(200),
            'story.paragraphs' => ['required', 'array', 'min:1', 'max:8'], 'story.paragraphs.*' => $text(1500),
            'story.quote' => $optional(400), 'story.quoteBy' => $optional(120), 'story.quoteSource' => $optional(120), 'story.quoteUrl' => ['nullable', 'url:https,http', 'max:500'],
            'figures.items' => ['present', 'array', 'max:8'],
            'figures.items.*.value' => $text(20), 'figures.items.*.label' => $text(160), 'figures.items.*.source' => $optional(120), 'figures.items.*.href' => ['nullable', 'url:https,http', 'max:500'],
            'materials.eyebrow' => $optional(80), 'materials.heading' => $text(200), 'materials.body' => $text(1000),
            'materials.items' => ['present', 'array', 'max:12'], 'materials.items.*.name' => $text(80), 'materials.items.*.body' => $text(400),
            'people.eyebrow' => $optional(80), 'people.heading' => $text(200), 'people.body' => $text(1000), 'people.caption' => $optional(200),
            ...$image('people'),
            'people.team' => ['present', 'array', 'max:12'], 'people.team.*.role' => $text(80), 'people.team.*.name' => $text(80),
            'ahenema.eyebrow' => $optional(80), 'ahenema.heading' => $text(200), 'ahenema.body' => $text(1000),
            ...$image('ahenema'),
            'experiences.eyebrow' => $optional(80), 'experiences.heading' => $text(200), 'experiences.body' => $text(600),
            'experiences.items' => ['present', 'array', 'max:8'], 'experiences.items.*.name' => $text(80), 'experiences.items.*.when' => $optional(120), 'experiences.items.*.detail' => $text(300),
            'watch.eyebrow' => $optional(80), 'watch.heading' => $text(200), 'watch.body' => $optional(600),
            'watch.posts' => ['present', 'array', 'max:30'],
            'watch.posts.*.platform' => ['required', 'in:youtube,instagram'],
            // A YouTube id or an Instagram shortcode: letters, digits, - and _.
            'watch.posts.*.id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{5,20}$/'],
            'watch.posts.*.title' => $text(120), 'watch.posts.*.meta' => $optional(120),
            'watch.posts.*.shape' => ['required', 'in:portrait,landscape'],
            'watch.posts.*.thumb' => ['nullable', 'in:oardefault,maxresdefault'],
            'slogans.items' => ['present', 'array', 'max:6'], 'slogans.items.*' => $text(60),
            'recognition.eyebrow' => $optional(80), 'recognition.heading' => $text(200),
            'recognition.items' => ['present', 'array', 'max:20'], 'recognition.items.*.name' => $text(120), 'recognition.items.*.detail' => $text(300), 'recognition.items.*.date' => $optional(40), 'recognition.items.*.href' => ['nullable', 'url:https,http', 'max:500'],
            'mission.heading' => $text(160), 'mission.lede' => $optional(400), 'mission.mission' => $text(1500), 'mission.vision' => $text(1500),
            'socials.instagram' => ['nullable', 'url:https', 'max:300'], 'socials.youtube' => ['nullable', 'url:https', 'max:300'],
        ]);
    }

    /** Saved content over the defaults, section by section. */
    public static function merged(?array $stored): array
    {
        $defaults = self::defaults();
        $out = [];
        foreach ($defaults as $section => $value) {
            $saved = $stored[$section] ?? null;
            // Defaults' keys are the schema; a saved section keeps only those,
            // and an image's default path can't be overwritten from outside.
            if (! is_array($saved)) {
                $out[$section] = $value;

                continue;
            }
            $merged = array_merge($value, array_intersect_key($saved, $value));
            if (isset($value['image'])) {
                $merged['image'] = $value['image'];
            }
            // Lists of records (figures, posts, team…) keep only the fields
            // their defaults have; lists of strings pass through.
            foreach ($merged as $key => $list) {
                $shape = $value[$key][0] ?? null;
                if (is_array($list) && is_array($shape) && array_is_list($list)) {
                    $merged[$key] = array_map(
                        fn ($item) => is_array($item) ? array_merge(array_fill_keys(array_keys($shape), null), array_intersect_key($item, $shape)) : $item,
                        $list,
                    );
                }
            }
            $out[$section] = $merged;
        }

        return $out;
    }

    /** For the storefront: merged, with each image block's `imageUrl` resolved. */
    public static function forStorefront(Page $page): array
    {
        $content = self::merged($page->content);
        $ids = collect(self::IMAGE_SECTIONS)->map(fn ($s) => $content[$s]['imageId'] ?? null)->filter()->all();
        $assets = $ids ? MediaAsset::query()->whereIn('id', $ids)->get()->keyBy('id') : collect();

        foreach (self::IMAGE_SECTIONS as $s) {
            $asset = isset($content[$s]['imageId']) ? $assets->get($content[$s]['imageId']) : null;
            $content[$s]['imageUrl'] = $asset?->url ?? $content[$s]['image'];
        }

        return $content;
    }

    /** @param  array<string, mixed>  $rules */
    private static function prefix(string $prefix, array $rules): array
    {
        return collect($rules)->mapWithKeys(fn ($rule, $key) => [$prefix.$key => $rule])->all();
    }
}

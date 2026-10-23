<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Support\AboutPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The About page is editable section by section from Content › Pages › About. */
class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    private function as(string $role): self
    {
        return $this->actingAs(AdminUser::factory()->create(['role' => $role]), 'admin');
    }

    private function save(array $content, string $role = 'staff'): TestResponse
    {
        return $this->as($role)->putJson('/api/v1/admin/pages/about', ['content' => $content]);
    }

    private function asset(): MediaAsset
    {
        return MediaAsset::query()->create([
            'path' => 'media/2026/10/team.jpg', 'filename' => 'team.jpg', 'mime_type' => 'image/jpeg',
            'size_bytes' => 1000, 'width' => 1600, 'height' => 1200, 'alt_text' => 'The team',
        ]);
    }

    public function test_with_nothing_saved_the_storefront_gets_every_section_from_the_defaults(): void
    {
        Page::query()->create(['slug' => 'about', 'title' => 'About', 'is_draft' => false]);

        $content = $this->getJson('/api/v1/pages/about')->assertOk()->json('data.content');

        $this->assertSame(AboutPage::SECTIONS, array_keys($content));
        $this->assertSame('/design/about-hero-ahotoso.webp', $content['hero']['imageUrl']);
        $this->assertCount(11, $content['watch']['posts']);
        // Hidden at the client's request, but kept for the admin.
        $this->assertFalse($content['slogans']['visible']);
        $this->assertNotEmpty($content['slogans']['items']);
    }

    public function test_staff_save_the_page_and_the_storefront_shows_it(): void
    {
        $content = AboutPage::defaults();
        $content['hero']['heading'] = 'Walk with purpose.';
        $content['figures']['items'][0]['value'] = '3,000+';
        $content['people']['team'][] = ['role' => 'Production Lead', 'name' => 'Isaaka'];
        $content['watch']['posts'] = array_slice($content['watch']['posts'], 0, 2);
        $content['recognition']['visible'] = false;

        $this->save($content)->assertOk()->assertJsonPath('data.content.hero.heading', 'Walk with purpose.');

        $public = $this->getJson('/api/v1/pages/about')->json('data.content');
        $this->assertSame('3,000+', $public['figures']['items'][0]['value']);
        $this->assertSame('Isaaka', $public['people']['team'][2]['name']);
        $this->assertCount(2, $public['watch']['posts']);
        $this->assertFalse($public['recognition']['visible']);
        $this->assertSame('<p>Handmade in Ghana, one pair at a time.</p>', Page::query()->where('slug', 'about')->value('body'));
    }

    public function test_a_chosen_image_replaces_the_default_until_it_is_deleted(): void
    {
        $asset = $this->asset();
        $content = AboutPage::defaults();
        $content['people']['imageId'] = $asset->id;

        $this->save($content)->assertOk()->assertJsonPath('data.images.0.filename', 'team.jpg');
        $this->assertStringEndsWith('media/2026/10/team.jpg', $this->getJson('/api/v1/pages/about')->json('data.content.people.imageUrl'));

        $asset->delete();
        $this->assertSame('/design/post-tyred-hero.png', $this->getJson('/api/v1/pages/about')->json('data.content.people.imageUrl'));
    }

    public function test_a_save_cannot_add_keys_or_point_an_image_elsewhere(): void
    {
        $content = AboutPage::defaults();
        $content['hero']['image'] = 'https://evil.example/x.png';
        $content['hero']['onclick'] = 'alert(1)';
        $content['extra'] = ['x' => 1];
        $content['figures']['items'][0]['onclick'] = 'alert(1)';

        $this->save($content)->assertOk();
        $stored = Page::query()->where('slug', 'about')->first()->content;

        $this->assertSame('/design/about-hero-ahotoso.webp', $stored['hero']['image']);
        $this->assertArrayNotHasKey('onclick', $stored['hero']);
        $this->assertArrayNotHasKey('extra', $stored);
        $this->assertArrayNotHasKey('onclick', $stored['figures']['items'][0]);
    }

    public function test_links_post_ids_and_required_text_are_checked(): void
    {
        $content = AboutPage::defaults();
        $content['figures']['items'][0]['href'] = 'javascript:alert(1)';
        $content['watch']['posts'][0]['id'] = '<script>';
        $content['story']['heading'] = '';

        $this->save($content)->assertUnprocessable()->assertJsonValidationErrors([
            'content.figures.items.0.href' => 'That link isn’t a full web address. Start it with https://.',
            'content.watch.posts.0.id' => 'That doesn’t look like a YouTube video ID or an Instagram reel code.',
            'content.story.heading' => 'Fill this in, or hide the section.',
        ]);
    }

    public function test_interns_can_read_but_not_save(): void
    {
        $this->as('intern')->getJson('/api/v1/admin/pages/about')->assertOk()->assertJsonPath('data.content.statement.text', 'Handmade in Ghana, one pair at a time.');
        $this->save(AboutPage::defaults(), 'intern')->assertForbidden();
    }
}

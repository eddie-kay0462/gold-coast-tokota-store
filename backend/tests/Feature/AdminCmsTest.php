<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Collection;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The native CMS surface (README Feature 9): blog, pages, site settings,
 * newsletter and catalogue taxonomy.
 */
class AdminCmsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'admin']);
    }

    private function staff(): AdminUser
    {
        return AdminUser::factory()->create(['role' => 'staff']);
    }

    // --- stored XSS -----------------------------------------------------

    /**
     * README Feature 9 edge case: PageEditor submissions must be sanitised
     * server-side. The browser is not the trust boundary — anything that can
     * POST here can post anything.
     */
    public function test_script_tags_are_stripped_from_a_blog_body_on_the_way_in(): void
    {
        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/blog', [
            'title' => 'A post',
            'body' => '<p>Real content</p><script>alert(1)</script>',
        ])->assertCreated();

        $body = BlogPost::first()->body;
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringContainsString('Real content', $body);
    }

    public function test_event_handlers_and_javascript_urls_are_stripped(): void
    {
        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/blog', [
            'title' => 'A post',
            'body' => '<p onclick="alert(1)">x</p><a href="javascript:alert(1)">y</a><img src=x onerror=alert(1)>',
        ])->assertCreated();

        $body = BlogPost::first()->body;
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('onerror', $body);
    }

    public function test_legitimate_formatting_survives_sanitisation(): void
    {
        $html = '<h2>Heading</h2><p>Text with <strong>bold</strong> and a '
            .'<a href="https://example.com">link</a>.</p><ul><li>one</li></ul>';

        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/blog', [
            'title' => 'A post',
            'body' => $html,
        ])->assertCreated();

        $body = BlogPost::first()->body;
        $this->assertStringContainsString('<h2>Heading</h2>', $body);
        $this->assertStringContainsString('<strong>bold</strong>', $body);
        $this->assertStringContainsString('href="https://example.com"', $body);
    }

    public function test_a_page_body_is_sanitised_on_update_too(): void
    {
        $page = Page::factory()->create();

        $this->actingAs($this->admin(), 'admin')->putJson("/api/v1/admin/pages/{$page->id}", [
            'body' => '<p>ok</p><iframe src="https://evil.test"></iframe>',
        ])->assertOk();

        $this->assertStringNotContainsString('<iframe', $page->fresh()->body);
    }

    // --- blog -----------------------------------------------------------

    public function test_blog_admin_list_includes_drafts(): void
    {
        BlogPost::factory()->create(['is_published' => true, 'published_at' => now()->subDay()]);
        BlogPost::factory()->create(['is_published' => false]);

        $response = $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/blog');

        $response->assertOk();
        // The public endpoint would show one; an editor has to see both.
        $this->assertCount(2, $response->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/blog-posts')->json('data'));
    }

    public function test_a_slug_is_derived_from_the_title_when_omitted(): void
    {
        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/blog', [
            'title' => 'The Story Of The Ahenema',
            'body' => '<p>x</p>',
        ])->assertCreated();

        $this->assertSame('the-story-of-the-ahenema', BlogPost::first()->slug);
    }

    /** A published post's slug is a URL somebody may already have linked to. */
    public function test_updating_a_title_does_not_silently_change_the_slug(): void
    {
        $post = BlogPost::factory()->create(['slug' => 'original-slug']);

        $this->actingAs($this->admin(), 'admin')
            ->putJson("/api/v1/admin/blog/{$post->id}", ['title' => 'A Completely New Title'])
            ->assertOk();

        $this->assertSame('original-slug', $post->fresh()->slug);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        BlogPost::factory()->create(['slug' => 'taken']);

        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/blog', [
            'title' => 'A post', 'body' => '<p>x</p>', 'slug' => 'taken',
        ])->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function test_staff_can_write_blog_posts(): void
    {
        $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/blog', [
            'title' => 'A post', 'body' => '<p>x</p>',
        ])->assertCreated();
    }

    public function test_a_blog_post_can_be_deleted(): void
    {
        $post = BlogPost::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson("/api/v1/admin/blog/{$post->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('blog_posts', ['id' => $post->id]);
    }

    // --- pages ----------------------------------------------------------

    /** Page slugs are storefront routes; admin edits them, never creates them. */
    public function test_pages_cannot_be_created_or_deleted_from_admin(): void
    {
        $page = Page::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->postJson('/api/v1/admin/pages', ['slug' => 'invented', 'title' => 'x', 'body' => 'y'])
            ->assertStatus(405);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson("/api/v1/admin/pages/{$page->id}")
            ->assertStatus(405);
    }

    public function test_a_page_edit_records_who_made_it(): void
    {
        $admin = $this->admin();
        $page = Page::factory()->create();

        $this->actingAs($admin, 'admin')
            ->putJson("/api/v1/admin/pages/{$page->id}", ['title' => 'Our Story'])
            ->assertOk();

        $this->assertSame($admin->id, $page->fresh()->updated_by_admin_id);
    }

    // --- site settings --------------------------------------------------

    public function test_staff_can_read_site_settings(): void
    {
        $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/site-settings')->assertOk();
    }

    /** README two-tier rule names Site Settings as Admin-only. */
    public function test_staff_cannot_write_site_settings(): void
    {
        SiteSetting::current()->update(['whatsapp_number' => '233200000000']);

        $this->actingAs($this->staff(), 'admin')
            ->putJson('/api/v1/admin/site-settings', ['whatsapp_number' => '233111111111'])
            ->assertForbidden();

        $this->assertSame('233200000000', SiteSetting::current()->whatsapp_number);
    }

    /**
     * The Feature 9 acceptance criterion: the owner changes the WhatsApp number
     * in admin and the storefront reflects it with no deploy.
     */
    public function test_an_admin_change_is_visible_on_the_public_endpoint_immediately(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->putJson('/api/v1/admin/site-settings', ['whatsapp_number' => '233555000111'])
            ->assertOk();

        $this->getJson('/api/v1/site-settings')
            ->assertOk()
            ->assertJsonPath('data.whatsapp_number', '233555000111');
    }

    /** It is interpolated into a wa.me URL; punctuation silently breaks the link. */
    public function test_a_whatsapp_number_with_punctuation_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->putJson('/api/v1/admin/site-settings', ['whatsapp_number' => '+233 20 000 0000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('whatsapp_number');
    }

    // --- newsletter and taxonomy ----------------------------------------

    public function test_staff_can_list_newsletter_subscribers(): void
    {
        NewsletterSubscriber::factory()->count(3)->create();

        $response = $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/newsletter');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_subscribers_export_as_csv(): void
    {
        NewsletterSubscriber::factory()->create(['email' => 'ama@example.com']);

        $response = $this->actingAs($this->admin(), 'admin')->get('/api/v1/admin/newsletter/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('ama@example.com', $response->streamedContent());
    }

    public function test_taxonomy_returns_categories_and_collections_together(): void
    {
        Category::factory()->create(['name' => 'Sandals']);
        Collection::factory()->create(['name' => 'Obrempong']);

        $response = $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/categories');

        $response->assertOk();
        $response->assertJsonPath('data.categories.0.name', 'Sandals');
        $response->assertJsonPath('data.collections.0.name', 'Obrempong');
    }

    public function test_every_cms_endpoint_is_closed_to_guests(): void
    {
        foreach (['/blog', '/pages', '/site-settings', '/newsletter', '/categories'] as $path) {
            $this->getJson("/api/v1/admin{$path}")->assertUnauthorized();
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Collection;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\Product;
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

    /** Staff write drafts; an Admin puts them live (`content.publish`). */
    public function test_staff_can_save_a_draft_but_not_publish_it(): void
    {
        $this->actingAs($this->staff(), 'admin')->postJson('/api/v1/admin/blog', [
            'title' => 'Draft', 'body' => '<p>x</p>', 'is_published' => false,
        ])->assertCreated();

        $post = BlogPost::first();

        $this->actingAs($this->staff(), 'admin')
            ->putJson("/api/v1/admin/blog/{$post->id}", ['is_published' => true])
            ->assertForbidden();
        $this->assertFalse($post->fresh()->is_published);

        // Editing the draft is still fine.
        $this->actingAs($this->staff(), 'admin')
            ->putJson("/api/v1/admin/blog/{$post->id}", ['title' => 'Better title', 'is_published' => false])
            ->assertOk();
    }

    /** Live means published *and* dated, so publishing without a date is now. */
    public function test_publishing_without_a_date_puts_the_post_live_now(): void
    {
        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/blog', [
            'title' => 'Live', 'body' => '<p>x</p>', 'is_published' => true,
        ])->assertCreated();

        $this->assertSame(1, BlogPost::query()->published()->count());
    }

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

    public function test_blog_admin_rows_carry_what_the_cms_table_reads(): void
    {
        BlogPost::factory()->create(['author' => null, 'is_published' => false]);

        $row = $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/blog')->json('data.0');

        // The storefront shape had none of these, and /blog crashed on
        // `authorName.split` the first time it read live data.
        $this->assertSame('', $row['author_name']);
        $this->assertFalse($row['is_published']);
        $this->assertArrayHasKey('updated_at', $row);
        $this->assertSame('', $row['excerpt']);
        $this->assertSame('', $row['meta_description']);
    }

    public function test_editorial_fields_are_saved(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/blog', [
            'title' => 'Inside the workshop',
            'body' => '<p>Body</p>',
            'author' => 'Gold Coast Tokota',
            'excerpt' => 'A morning at the bench.',
            'cover_image_alt' => 'Hands stitching a strap',
            'meta_description' => 'How a pair is made.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.author_name', 'Gold Coast Tokota');
        $response->assertJsonPath('data.excerpt', 'A morning at the bench.');
        $response->assertJsonPath('data.cover_image_alt', 'Hands stitching a strap');
        $response->assertJsonPath('data.meta_description', 'How a pair is made.');
    }

    public function test_a_meta_description_longer_than_a_search_snippet_is_refused(): void
    {
        $this->actingAs($this->admin(), 'admin')->postJson('/api/v1/admin/blog', [
            'title' => 'Too long',
            'body' => '<p>Body</p>',
            'meta_description' => str_repeat('a', 201),
        ])->assertUnprocessable()->assertJsonValidationErrors('meta_description');
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

    /** The product form fills Type in from the category, so each one says its usual type. */
    public function test_each_category_names_the_type_most_of_its_products_carry(): void
    {
        $shoes = Category::factory()->create(['name' => 'Shoes']);
        Category::factory()->create(['name' => 'Empty']);
        Product::factory()->count(2)->create(['category_id' => $shoes->id, 'product_type' => 'closed-toe']);
        Product::factory()->create(['category_id' => $shoes->id, 'product_type' => 'shoes']);

        $this->actingAs($this->staff(), 'admin')->getJson('/api/v1/admin/categories')
            ->assertJsonPath('data.categories.0.name', 'Empty')
            ->assertJsonPath('data.categories.0.product_type', null)
            ->assertJsonPath('data.categories.1.product_type', 'closed-toe');
    }

    public function test_an_admin_can_add_rename_and_delete_an_empty_category(): void
    {
        $id = $this->actingAs($this->admin(), 'admin')
            ->postJson('/api/v1/admin/categories', ['name' => 'Kids Sandals'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'kids-sandals')
            ->assertJsonPath('data.product_count', 0)
            ->json('data.id');

        $this->actingAs($this->admin(), 'admin')
            ->putJson("/api/v1/admin/categories/{$id}", ['name' => 'Kids', 'slug' => 'kids'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Kids');

        $this->actingAs($this->admin(), 'admin')->deleteJson("/api/v1/admin/categories/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $id]);
    }

    public function test_a_category_with_products_is_not_deleted(): void
    {
        $category = Category::factory()->create(['name' => 'Sandals']);
        Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->admin(), 'admin')
            ->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertStatus(422);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_staff_cannot_add_a_category(): void
    {
        $this->actingAs($this->staff(), 'admin')
            ->postJson('/api/v1/admin/categories', ['name' => 'Kids'])
            ->assertForbidden();
    }

    public function test_every_cms_endpoint_is_closed_to_guests(): void
    {
        foreach (['/blog', '/pages', '/site-settings', '/newsletter', '/categories'] as $path) {
            $this->getJson("/api/v1/admin{$path}")->assertUnauthorized();
        }
    }

    public function test_the_page_editor_addresses_a_page_by_slug(): void
    {
        $page = Page::query()->create(['slug' => 'privacy', 'title' => 'Privacy Policy', 'body' => '<p>Old</p>', 'is_draft' => true]);

        $this->actingAs($this->admin(), 'admin')->getJson('/api/v1/admin/pages/privacy')->assertOk()->assertJsonPath('data.id', $page->id);
        $this->actingAs($this->admin(), 'admin')
            ->putJson('/api/v1/admin/pages/privacy', ['body' => '<p>New</p>'])
            ->assertOk()
            ->assertJsonPath('data.body', '<p>New</p>');
    }
}

<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_published_posts(): void
    {
        BlogPost::factory()->count(2)->create();
        BlogPost::factory()->unpublished()->create();

        $response = $this->getJson('/api/v1/blog-posts');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_excludes_scheduled_posts_with_a_future_published_at(): void
    {
        BlogPost::factory()->create();
        BlogPost::factory()->scheduled()->create();

        $response = $this->getJson('/api/v1/blog-posts');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_paginates(): void
    {
        BlogPost::factory()->count(12)->create();

        $response = $this->getJson('/api/v1/blog-posts');

        $response->assertOk();
        $this->assertCount(9, $response->json('data'));
        $response->assertJsonPath('meta.total', 12);
    }

    public function test_show_returns_published_post_by_slug(): void
    {
        $post = BlogPost::factory()->create(['slug' => 'the-story-of-the-ahenema']);

        $response = $this->getJson('/api/v1/blog-posts/the-story-of-the-ahenema');

        $response->assertOk();
        $response->assertJsonPath('data.id', $post->id);
    }

    public function test_show_returns_404_for_unpublished_post(): void
    {
        BlogPost::factory()->unpublished()->create(['slug' => 'hidden-post']);

        $this->getJson('/api/v1/blog-posts/hidden-post')->assertNotFound();
    }

    public function test_show_returns_404_for_missing_slug(): void
    {
        $this->getJson('/api/v1/blog-posts/does-not-exist')->assertNotFound();
    }
}

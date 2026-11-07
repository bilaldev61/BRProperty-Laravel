<?php

namespace Tests\Feature;

use App\Support\Blog;
use Tests\TestCase;

class BlogTest extends TestCase
{
    public function test_the_blog_listing_renders_latest_posts(): void
    {
        $response = $this->get('/blog')->assertOk();

        foreach (Blog::latest() as $post) {
            $response->assertSee($post['title']);
        }
    }

    public function test_a_single_post_renders_with_its_body(): void
    {
        $post = Blog::latest()[0];

        $this->get('/blog/' . $post['slug'])
            ->assertOk()
            ->assertSee($post['title'])
            ->assertSee($post['body'][0]);
    }

    public function test_an_unknown_post_slug_is_not_found(): void
    {
        $this->get('/blog/no-such-article')->assertNotFound();
    }

    public function test_blog_pages_carry_a_title_description_and_one_heading(): void
    {
        $slug = Blog::latest()[0]['slug'];

        foreach (['/blog', "/blog/{$slug}"] as $uri) {
            $html = $this->get($uri)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<title>.{10,}<\/title>/', $html, $uri);
            $this->assertStringContainsString('<meta name="description"', $html, $uri);
            $this->assertStringContainsString('<link rel="canonical"', $html, $uri);
            $this->assertSame(1, substr_count($html, '<h1'), "{$uri} should have exactly one h1");
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature Tests for Trending Discussions
 * 
 * Tests trending discussions display, caching, and cache invalidation.
 * Requirements: 9.2
 */
class TrendingDiscussionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test homepage displays trending discussions ordered by comment count
     * 
     * Requirements: 9.2
     */
    public function test_homepage_displays_trending_discussions_ordered_by_comment_count(): void
    {
        // Create URLs with different comment counts
        $url1 = Url::factory()->create(['comment_count' => 5, 'title' => 'First Discussion']);
        $url2 = Url::factory()->create(['comment_count' => 15, 'title' => 'Second Discussion']);
        $url3 = Url::factory()->create(['comment_count' => 10, 'title' => 'Third Discussion']);
        $url4 = Url::factory()->create(['comment_count' => 0, 'title' => 'Fourth Discussion']);

        // Visit homepage
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Assert trending discussions section is present
        $response->assertSee('Trending Discussions');
        
        // Assert discussions are ordered by comment count (descending)
        $response->assertSeeInOrder([
            'Second Discussion',  // 15 comments
            'Third Discussion',   // 10 comments
            'First Discussion',   // 5 comments
            'Fourth Discussion',  // 0 comments
        ]);
    }

    /**
     * Test homepage displays only top 10 trending discussions
     * 
     * Requirements: 9.2
     */
    public function test_homepage_displays_only_top_10_trending_discussions(): void
    {
        // Create 15 URLs with different comment counts
        for ($i = 1; $i <= 15; $i++) {
            Url::factory()->create([
                'comment_count' => $i,
                'title' => "Unique Discussion Title {$i} End",
            ]);
        }

        // Visit homepage
        $response = $this->get('/');

        $response->assertStatus(200);
        
        // Assert top 10 are visible (6-15)
        for ($i = 15; $i >= 6; $i--) {
            $response->assertSee("Unique Discussion Title {$i} End");
        }
        
        // Assert bottom 5 are not visible (1-5)
        for ($i = 1; $i <= 5; $i++) {
            $response->assertDontSee("Unique Discussion Title {$i} End");
        }
    }

    /**
     * Test trending discussions are cached for 5 minutes
     * 
     * Requirements: 9.2
     */
    public function test_trending_discussions_are_cached_for_5_minutes(): void
    {
        // Create initial URL
        $url1 = Url::factory()->create(['comment_count' => 10, 'title' => 'Cached Discussion']);

        // First request - should cache
        $response1 = $this->get('/');
        $response1->assertSee('Cached Discussion');

        // Create new URL with higher comment count
        $url2 = Url::factory()->create(['comment_count' => 20, 'title' => 'New Discussion']);

        // Second request - should still show cached results
        $response2 = $this->get('/');
        $response2->assertSee('Cached Discussion');
        $response2->assertDontSee('New Discussion');

        // Clear cache
        Cache::forget('trending:discussions');

        // Third request - should show updated results
        $response3 = $this->get('/');
        $response3->assertSee('New Discussion');
        $response3->assertSee('Cached Discussion');
    }

    /**
     * Test cache is invalidated when new comment is posted
     * 
     * Requirements: 9.2
     */
    public function test_cache_is_invalidated_when_new_comment_is_posted(): void
    {
        // Create URLs
        $url1 = Url::factory()->create(['comment_count' => 5, 'title' => 'First Discussion']);
        $url2 = Url::factory()->create(['comment_count' => 10, 'title' => 'Second Discussion']);

        // Load homepage to cache trending discussions
        $this->get('/');
        
        // Verify cache exists
        $this->assertTrue(Cache::has('trending:discussions'));

        // Post a comment to url1
        $this->postJson("/d/{$url1->slug}/comment", [
            'body' => 'Test comment',
            'sentiment' => 'positive',
            'guest_name' => 'Test User',
        ]);

        // Verify cache was invalidated
        $this->assertFalse(Cache::has('trending:discussions'));
    }

    /**
     * Test trending discussions display correct metadata
     * 
     * Requirements: 9.2
     */
    public function test_trending_discussions_display_correct_metadata(): void
    {
        // Create URL with full metadata
        $url = Url::factory()->create([
            'comment_count' => 10,
            'title' => 'Test Article Title',
            'description' => 'This is a test description',
            'domain' => 'example.com',
            'thumbnail_url' => 'https://example.com/image.jpg',
        ]);

        // Visit homepage
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Test Article Title');
        $response->assertSee('This is a test description');
        $response->assertSee('example.com');
        $response->assertSee('10 comments');
        $response->assertSee('https://example.com/image.jpg');
    }

    /**
     * Test trending discussions display domain when title is missing
     * 
     * Requirements: 9.2
     */
    public function test_trending_discussions_display_domain_when_title_is_missing(): void
    {
        // Create URL without title
        $url = Url::factory()->create([
            'comment_count' => 5,
            'title' => null,
            'domain' => 'example.com',
        ]);

        // Visit homepage
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('example.com');
    }

    /**
     * Test trending discussions are clickable links to discussion pages
     * 
     * Requirements: 9.2
     */
    public function test_trending_discussions_are_clickable_links_to_discussion_pages(): void
    {
        // Create URL
        $url = Url::factory()->create([
            'comment_count' => 10,
            'title' => 'Clickable Discussion',
            'slug' => 'abc12345',
        ]);

        // Visit homepage
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('/d/abc12345');
    }

    /**
     * Test homepage shows no trending section when no discussions exist
     * 
     * Requirements: 9.2
     */
    public function test_homepage_shows_no_trending_section_when_no_discussions_exist(): void
    {
        // Visit homepage with no URLs
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('Trending Discussions');
    }
}

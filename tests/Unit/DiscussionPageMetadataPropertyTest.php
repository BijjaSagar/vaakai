<?php

namespace Tests\Unit;

use App\Models\Url;
use Eris\Generator;
use Eris\TestTrait;
use Tests\TestCase;

/**
 * Property 7: Discussion Page Renders All Metadata Fields
 * 
 * For any Url record, rendering the discussion page at /d/{slug} must produce HTML 
 * that contains the URL's title, description, domain, and comment count.
 * 
 * Feature: speakspace, Property 7: Discussion Page Renders All Metadata Fields
 * 
 * **Validates: Requirements 4.1**
 */
class DiscussionPageMetadataPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * @test
     */
    public function discussion_page_renders_all_metadata_fields()
    {
        $this->limitTo(5)
            ->forAll(
            Generator\choose(1, 100), // comment_count
            Generator\choose(1, 10) // iterations
        )
        ->then(function ($commentCount, $iteration) {
            // Generate random metadata
            $title = 'Test Title ' . $iteration;
            $description = 'Test Description ' . $iteration;
            $domain = ['example.com', 'test.org', 'news.site', 'blog.io'][array_rand(['example.com', 'test.org', 'news.site', 'blog.io'])];

            // Create a URL record with random metadata
            $url = Url::create([
                'url_hash' => md5(uniqid()),
                'original_url' => "https://{$domain}/test",
                'normalized_url' => "https://{$domain}/test",
                'slug' => substr(md5(uniqid()), 0, 8),
                'title' => $title,
                'description' => $description,
                'thumbnail_url' => "https://{$domain}/image.jpg",
                'domain' => $domain,
                'og_fetched_at' => now(),
                'comment_count' => $commentCount,
            ]);

            // Render the discussion page
            $response = $this->get("/d/{$url->slug}");

            // Assert the response is successful
            $response->assertStatus(200);

            // Assert all metadata fields are present in the HTML
            $html = $response->getContent();
            
            // Title should be present
            $this->assertStringContainsString($title, $html, 
                "Title '{$title}' not found in rendered HTML");
            
            // Description should be present
            $this->assertStringContainsString($description, $html, 
                "Description '{$description}' not found in rendered HTML");
            
            // Domain should be present
            $this->assertStringContainsString($domain, $html, 
                "Domain '{$domain}' not found in rendered HTML");
            
            // Comment count should be present
            $this->assertStringContainsString((string)$commentCount, $html, 
                "Comment count '{$commentCount}' not found in rendered HTML");
        });
    }

    /**
     * @test
     */
    public function discussion_page_returns_404_for_unknown_slug()
    {
        $this->limitTo(5)
            ->forAll(
            Generator\choose(1, 10) // iterations
        )
        ->then(function ($iteration) {
            $randomSlug = 'test' . $iteration;

            // Ensure the slug doesn't exist
            if (Url::where('slug', $randomSlug)->exists()) {
                return;
            }

            // Attempt to access a non-existent discussion
            $response = $this->get("/d/{$randomSlug}");

            // Assert 404 response
            $response->assertStatus(404);
        });
    }
}


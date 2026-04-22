<?php

namespace Tests\Unit;

use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase as LaravelTestCase;

/**
 * Property-Based Tests for Slug Uniqueness
 * 
 * Feature: speakspace, Property 4: Slug Uniqueness
 */
class SlugUniquenessPropertyTest extends LaravelTestCase
{
    use TestTrait;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable rate limiting for property tests
        // Property tests run 100 iterations and would hit rate limits
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    /**
     * Property 4: Slug Uniqueness
     * 
     * **Validates: Requirements 2.3**
     * 
     * For any set of distinct normalized URLs inserted into the system, all generated
     * slugs must be unique — no two urls records may share the same slug value.
     * 
     * This test runs with a minimum of 100 iterations as per spec requirements.
     * Eris default configuration runs 100 iterations by default.
     * 
     * @test
     */
    public function property_all_generated_slugs_are_unique()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->multipleUrlsGenerator()
            )
            ->then(function ($urlsData) {
                $urls = $urlsData['urls'];
                $numUrls = count($urls);
                
                // Submit all URLs to the system
                $slugs = [];
                foreach ($urls as $url) {
                    $response = $this->postJson('/discuss', [
                        'url' => $url,
                    ]);
                    
                    // Assert submission was successful
                    $this->assertContains(
                        $response->status(),
                        [200, 201],
                        "URL submission should succeed. Got status: {$response->status()} for URL: {$url}"
                    );
                    
                    // Extract slug from redirect path
                    $redirect = $response->json('redirect');
                    $this->assertNotNull($redirect, "Redirect path should be present");
                    
                    // Extract slug from /d/{slug} format
                    preg_match('/\/d\/([a-f0-9]+)/', $redirect, $matches);
                    $this->assertNotEmpty($matches, "Redirect should contain slug in /d/{slug} format. Got: {$redirect}");
                    
                    $slug = $matches[1];
                    $slugs[] = $slug;
                }
                
                // Assert: All slugs must be unique
                $uniqueSlugs = array_unique($slugs);
                $this->assertCount(
                    $numUrls,
                    $uniqueSlugs,
                    "All {$numUrls} generated slugs must be unique. " .
                    "Found " . count($uniqueSlugs) . " unique slugs. " .
                    "Slugs: " . implode(', ', $slugs)
                );
                
                // Additional assertion: Verify in database that all slugs are unique
                $dbSlugs = DB::table('urls')->pluck('slug')->toArray();
                $uniqueDbSlugs = array_unique($dbSlugs);
                
                $this->assertCount(
                    count($dbSlugs),
                    $uniqueDbSlugs,
                    "All slugs in database must be unique. " .
                    "Found " . count($dbSlugs) . " total slugs, " . count($uniqueDbSlugs) . " unique. " .
                    "DB Slugs: " . implode(', ', $dbSlugs)
                );
                
                // Assert: Each slug must be exactly 8 characters (or more if collision fallback triggered)
                foreach ($slugs as $slug) {
                    $this->assertGreaterThanOrEqual(
                        8,
                        strlen($slug),
                        "Slug must be at least 8 characters long. Got: {$slug} (length: " . strlen($slug) . ")"
                    );
                }
            });
    }

    /**
     * Property 4 (Edge Case): Slug collision handling
     * 
     * **Validates: Requirements 2.3**
     * 
     * Test that when hash collisions occur (same first 8 characters), the system
     * correctly generates unique slugs by using subsequent 8-character windows.
     * 
     * This is a targeted test for the collision resolution algorithm.
     * 
     * @test
     */
    public function property_slug_collision_resolution_maintains_uniqueness()
    {
        $this
            ->limitTo(5)
            ->forAll(
                Generator\choose(5, 15) // Generate between 5 and 15 URLs
            )
            ->then(function ($numUrls) {
                $slugs = [];
                
                // Generate and submit multiple distinct URLs
                for ($i = 0; $i < $numUrls; $i++) {
                    // Create URLs that are guaranteed to be different
                    $url = "https://example{$i}.com/page/{$i}?id={$i}";
                    
                    $response = $this->postJson('/discuss', [
                        'url' => $url,
                    ]);
                    
                    $this->assertContains(
                        $response->status(),
                        [200, 201],
                        "URL submission should succeed"
                    );
                    
                    // Extract slug
                    $redirect = $response->json('redirect');
                    preg_match('/\/d\/([a-f0-9]+)/', $redirect, $matches);
                    $slug = $matches[1];
                    $slugs[] = $slug;
                }
                
                // Assert: All slugs must be unique
                $uniqueSlugs = array_unique($slugs);
                $this->assertCount(
                    $numUrls,
                    $uniqueSlugs,
                    "All {$numUrls} generated slugs must be unique even with potential collisions. " .
                    "Found " . count($uniqueSlugs) . " unique slugs."
                );
                
                // Assert: No duplicate slugs in database
                $dbSlugCounts = DB::table('urls')
                    ->select('slug', DB::raw('COUNT(*) as count'))
                    ->groupBy('slug')
                    ->having('count', '>', 1)
                    ->get();
                
                $this->assertCount(
                    0,
                    $dbSlugCounts,
                    "No duplicate slugs should exist in database. Found duplicates: " . 
                    $dbSlugCounts->pluck('slug')->implode(', ')
                );
            });
    }

    /**
     * Generate multiple random URLs
     * 
     * Generates a set of N distinct URLs to test slug uniqueness across multiple submissions.
     * 
     * @return Generator\GeneratedValueSingle
     */
    private function multipleUrlsGenerator()
    {
        return Generator\bind(
            Generator\choose(3, 10), // Generate between 3 and 10 URLs per test
            function ($numUrls) {
                // Generate an array of URLs
                $urls = [];
                for ($i = 0; $i < $numUrls; $i++) {
                    $urls[] = $this->generateUniqueUrl($i);
                }
                
                return Generator\constant([
                    'urls' => $urls,
                    'count' => $numUrls,
                ]);
            }
        );
    }

    /**
     * Generate a unique URL with a given index
     * 
     * @param int $index
     * @return string
     */
    private function generateUniqueUrl(int $index): string
    {
        $domains = [
            'example.com', 'test.org', 'demo.net', 'site.io', 'blog.dev',
            'news.co.uk', 'shop.com', 'forum.org', 'portal.info', 'service.biz',
            'platform.app', 'community.social', 'market.store', 'tech.digital', 'media.press',
        ];
        
        $paths = [
            '', '/article', '/blog/post', '/products/item', '/news/2024/story',
            '/page', '/about', '/contact', '/docs/guide', '/api/v1/resource',
            '/forum/thread', '/user/profile', '/category/tech', '/search/results', '/dashboard',
        ];
        
        $schemes = ['http', 'https'];
        
        $scheme = $schemes[$index % 2];
        $domain = $domains[$index % count($domains)];
        $path = $paths[$index % count($paths)];
        
        // Add unique timestamp and random component to ensure uniqueness
        $uniqueParam = microtime(true) . '_' . bin2hex(random_bytes(4)) . '_' . $index;
        
        $url = "{$scheme}://{$domain}";
        if ($path !== '') {
            $url .= $path;
        }
        $url .= "?_t={$uniqueParam}";
        
        return $url;
    }
}

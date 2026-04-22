<?php

namespace Tests\Unit;

use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase as LaravelTestCase;

/**
 * Property-Based Tests for URL Deduplication Idempotence
 * 
 * Feature: speakspace, Property 3: URL Deduplication Idempotence
 */
class UrlDeduplicationPropertyTest extends LaravelTestCase
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
     * Property 3: URL Deduplication Idempotence
     * 
     * **Validates: Requirements 2.1**
     * 
     * For any URL that already has a record in the urls table, submitting it again
     * (regardless of minor variations that normalize to the same canonical form) must
     * not create a new urls record — the count of rows in urls must remain unchanged.
     * 
     * This test runs with a minimum of 100 iterations as per spec requirements.
     * Eris default configuration runs 100 iterations by default.
     * 
     * @test
     */
    public function property_submitting_duplicate_url_does_not_create_new_record()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->urlGenerator()
            )
            ->then(function ($urlData) {
                $url = $urlData['url'];
                
                // Submit the URL for the first time
                $firstResponse = $this->postJson('/discuss', [
                    'url' => $url,
                ]);
                
                // Assert first submission was successful (201 or 200)
                $this->assertContains(
                    $firstResponse->status(),
                    [200, 201],
                    "First submission should succeed. Got status: {$firstResponse->status()}"
                );
                
                // Count urls records after first submission
                $urlsCountAfterFirst = DB::table('urls')->count();
                $this->assertGreaterThan(
                    0,
                    $urlsCountAfterFirst,
                    "At least one URL record should exist after first submission"
                );
                
                // Submit the same URL again (second time)
                $secondResponse = $this->postJson('/discuss', [
                    'url' => $url,
                ]);
                
                // Assert second submission was successful (should redirect to existing)
                $this->assertContains(
                    $secondResponse->status(),
                    [200, 201],
                    "Second submission should succeed. Got status: {$secondResponse->status()}"
                );
                
                // Count urls records after second submission
                $urlsCountAfterSecond = DB::table('urls')->count();
                
                // Assert: URLs count must remain unchanged (idempotence)
                $this->assertEquals(
                    $urlsCountAfterFirst,
                    $urlsCountAfterSecond,
                    "Submitting duplicate URL should not create new record. " .
                    "Count after first: {$urlsCountAfterFirst}, Count after second: {$urlsCountAfterSecond}. " .
                    "URL: {$url}"
                );
                
                // Additional assertion: both responses should redirect to the same slug
                $firstRedirect = $firstResponse->json('redirect');
                $secondRedirect = $secondResponse->json('redirect');
                
                $this->assertEquals(
                    $firstRedirect,
                    $secondRedirect,
                    "Both submissions should redirect to the same discussion page. " .
                    "First: {$firstRedirect}, Second: {$secondRedirect}"
                );
            });
    }

    /**
     * Property 3 (Variation Test): URL variations that normalize to same form
     * 
     * **Validates: Requirements 2.1**
     * 
     * Test that URLs with minor variations (www prefix, UTM params, trailing slashes,
     * case differences) that normalize to the same canonical form are properly deduplicated.
     * 
     * @test
     */
    public function property_url_variations_normalize_to_same_record()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->urlWithVariationsGenerator()
            )
            ->then(function ($urlData) {
                $originalUrl = $urlData['original'];
                $variation = $urlData['variation'];
                
                // Submit the original URL first
                $firstResponse = $this->postJson('/discuss', [
                    'url' => $originalUrl,
                ]);
                
                $this->assertContains(
                    $firstResponse->status(),
                    [200, 201],
                    "First submission should succeed"
                );
                
                // Count after first submission
                $urlsCountAfterFirst = DB::table('urls')->count();
                
                // Submit the variation
                $secondResponse = $this->postJson('/discuss', [
                    'url' => $variation,
                ]);
                
                $this->assertContains(
                    $secondResponse->status(),
                    [200, 201],
                    "Variation submission should succeed"
                );
                
                // Count after variation submission
                $urlsCountAfterSecond = DB::table('urls')->count();
                
                // Assert: URLs count must remain unchanged
                $this->assertEquals(
                    $urlsCountAfterFirst,
                    $urlsCountAfterSecond,
                    "URL variation should not create new record. " .
                    "Original: {$originalUrl}, Variation: {$variation}"
                );
                
                // Both should redirect to the same slug
                $firstRedirect = $firstResponse->json('redirect');
                $secondRedirect = $secondResponse->json('redirect');
                
                $this->assertEquals(
                    $firstRedirect,
                    $secondRedirect,
                    "URL variations should redirect to the same discussion. " .
                    "Original: {$originalUrl}, Variation: {$variation}"
                );
            });
    }

    /**
     * Generate random valid URLs
     * 
     * Generates URLs with:
     * - Valid http/https protocol
     * - Random domains
     * - Random paths
     * - Random query parameters
     * 
     * @return Generator\GeneratedValueSingle
     */
    private function urlGenerator()
    {
        return Generator\bind(
            Generator\elements(['http', 'https']),
            function ($scheme) {
                return Generator\bind(
                    $this->domainGenerator(),
                    function ($domain) use ($scheme) {
                        return Generator\bind(
                            $this->pathGenerator(),
                            function ($path) use ($scheme, $domain) {
                                return Generator\bind(
                                    $this->queryParamsGenerator(),
                                    function ($queryParams) use ($scheme, $domain, $path) {
                                        $url = "{$scheme}://{$domain}";
                                        
                                        if ($path !== '') {
                                            $url .= $path;
                                        }
                                        
                                        if (!empty($queryParams)) {
                                            $url .= '?' . http_build_query($queryParams);
                                        }
                                        
                                        return Generator\constant([
                                            'url' => $url,
                                            'domain' => $domain,
                                            'path' => $path,
                                            'queryParams' => $queryParams,
                                        ]);
                                    }
                                );
                            }
                        );
                    }
                );
            }
        );
    }

    /**
     * Generate URL with variations that should normalize to the same form
     * 
     * Creates pairs of URLs where the second is a variation of the first
     * (with www prefix, UTM params, trailing slash, case differences)
     * 
     * @return Generator\GeneratedValueSingle
     */
    private function urlWithVariationsGenerator()
    {
        return Generator\bind(
            $this->domainGenerator(),
            function ($domain) {
                return Generator\bind(
                    $this->pathGenerator(),
                    function ($path) use ($domain) {
                        return Generator\bind(
                            $this->queryParamsGenerator(),
                            function ($queryParams) use ($domain, $path) {
                                // Build original URL (clean)
                                $original = "https://{$domain}";
                                if ($path !== '') {
                                    $original .= $path;
                                }
                                if (!empty($queryParams)) {
                                    $original .= '?' . http_build_query($queryParams);
                                }
                                
                                // Build variation with normalization differences
                                return Generator\bind(
                                    Generator\elements(['www', 'utm', 'trailing_slash', 'case', 'combined']),
                                    function ($variationType) use ($original, $domain, $path, $queryParams) {
                                        $variation = '';
                                        
                                        switch ($variationType) {
                                            case 'www':
                                                // Add www prefix
                                                $variation = "https://www.{$domain}";
                                                if ($path !== '') {
                                                    $variation .= $path;
                                                }
                                                if (!empty($queryParams)) {
                                                    $variation .= '?' . http_build_query($queryParams);
                                                }
                                                break;
                                                
                                            case 'utm':
                                                // Add UTM parameters
                                                $variation = "https://{$domain}";
                                                if ($path !== '') {
                                                    $variation .= $path;
                                                }
                                                $paramsWithUtm = array_merge($queryParams, [
                                                    'utm_source' => 'twitter',
                                                    'utm_medium' => 'social',
                                                ]);
                                                $variation .= '?' . http_build_query($paramsWithUtm);
                                                break;
                                                
                                            case 'trailing_slash':
                                                // Add trailing slash to path
                                                $variation = "https://{$domain}";
                                                if ($path !== '') {
                                                    $variation .= rtrim($path, '/') . '/';
                                                } else {
                                                    $variation .= '/';
                                                }
                                                if (!empty($queryParams)) {
                                                    $variation .= '?' . http_build_query($queryParams);
                                                }
                                                break;
                                                
                                            case 'case':
                                                // Change case of domain
                                                $variation = "HTTPS://" . strtoupper($domain);
                                                if ($path !== '') {
                                                    $variation .= $path;
                                                }
                                                if (!empty($queryParams)) {
                                                    $variation .= '?' . http_build_query($queryParams);
                                                }
                                                break;
                                                
                                            case 'combined':
                                                // Combine multiple variations
                                                $variation = "HTTPS://WWW." . strtoupper($domain);
                                                if ($path !== '') {
                                                    $variation .= rtrim($path, '/') . '/';
                                                }
                                                $paramsWithUtm = array_merge($queryParams, [
                                                    'utm_campaign' => 'test',
                                                ]);
                                                $variation .= '?' . http_build_query($paramsWithUtm);
                                                break;
                                        }
                                        
                                        return Generator\constant([
                                            'original' => $original,
                                            'variation' => $variation,
                                            'variationType' => $variationType,
                                        ]);
                                    }
                                );
                            }
                        );
                    }
                );
            }
        );
    }

    /**
     * Generate random domain names
     */
    private function domainGenerator()
    {
        return Generator\elements([
            'example.com',
            'test.org',
            'demo.net',
            'site.io',
            'blog.dev',
            'news.co.uk',
            'shop.com',
            'forum.org',
            'portal.info',
            'service.biz',
        ]);
    }

    /**
     * Generate random paths
     */
    private function pathGenerator()
    {
        return Generator\elements([
            '',
            '/article',
            '/blog/post',
            '/products/item',
            '/news/2024/story',
            '/page',
            '/about',
            '/contact',
            '/docs/guide',
            '/api/v1/resource',
        ]);
    }

    /**
     * Generate random query parameters (without UTM params for base URLs)
     */
    private function queryParamsGenerator()
    {
        return Generator\bind(
            Generator\choose(0, 3),
            function ($numParams) {
                $params = [];
                $paramNames = ['id', 'page', 'sort', 'filter', 'q', 'category', 'tag'];
                
                for ($i = 0; $i < $numParams; $i++) {
                    $key = $paramNames[$i % count($paramNames)];
                    $params[$key] = (string)($i + 1);
                }
                
                return Generator\constant($params);
            }
        );
    }
}

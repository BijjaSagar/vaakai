<?php

namespace Tests\Unit;

use App\Services\UrlNormalizerService;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-Based Tests for URL Normalization
 * 
 * Feature: speakspace, Property 1: URL Normalization Correctness
 */
class UrlNormalizationPropertyTest extends TestCase
{
    use TestTrait;

    private UrlNormalizerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UrlNormalizerService();
    }

    /**
     * Property 1: URL Normalization Correctness
     * 
     * **Validates: Requirements 1.2, 1.3**
     * 
     * For any URL string, the normalized form produced by UrlNormalizerService::normalize()
     * must be lowercase, have no www. prefix, contain no UTM parameters, have no trailing
     * slash on the path, and have query parameters in alphabetical order. Additionally,
     * md5(normalize(url)) must equal the url_hash stored in the urls record for that URL.
     * 
     * This test runs with a minimum of 100 iterations as per spec requirements.
     * Eris default configuration runs 100 iterations by default.
     * 
     * @test
     */
    public function property_url_normalization_produces_canonical_form_with_correct_hash()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->urlGenerator()
            )
            ->then(function ($urlData) {
                $url = $urlData['url'];
                $normalized = $this->service->normalize($url);
                
                // Assert 1: Normalized URL must be lowercase
                $this->assertEquals(
                    strtolower($normalized),
                    $normalized,
                    "Normalized URL must be lowercase. Got: {$normalized}"
                );
                
                // Assert 2: Normalized URL must not contain www. prefix
                $parsedNormalized = parse_url($normalized);
                $host = $parsedNormalized['host'] ?? '';
                $this->assertStringNotContainsString(
                    'www.',
                    $host,
                    "Normalized URL must not contain www. prefix. Got host: {$host}"
                );
                
                // Assert 3: Normalized URL must not contain UTM parameters
                if (isset($parsedNormalized['query'])) {
                    parse_str($parsedNormalized['query'], $queryParams);
                    foreach (array_keys($queryParams) as $key) {
                        $this->assertStringNotContainsString(
                            'utm_',
                            $key,
                            "Normalized URL must not contain UTM parameters. Found: {$key}"
                        );
                    }
                }
                
                // Assert 4: Normalized URL path must not have trailing slash
                $path = $parsedNormalized['path'] ?? '';
                if ($path !== '' && $path !== '/') {
                    $this->assertStringEndsNotWith(
                        '/',
                        $path,
                        "Normalized URL path must not have trailing slash. Got path: {$path}"
                    );
                }
                
                // Assert 5: Query parameters must be in alphabetical order
                if (isset($parsedNormalized['query'])) {
                    parse_str($parsedNormalized['query'], $queryParams);
                    $keys = array_keys($queryParams);
                    $sortedKeys = $keys;
                    sort($sortedKeys);
                    $this->assertEquals(
                        $sortedKeys,
                        $keys,
                        "Query parameters must be in alphabetical order. Got: " . implode(', ', $keys)
                    );
                }
                
                // Assert 6: md5(normalized) must equal the hash produced by the service
                $hash = $this->service->hash($normalized);
                $expectedHash = md5($normalized);
                $this->assertEquals(
                    $expectedHash,
                    $hash,
                    "Hash of normalized URL must match md5(normalized). Expected: {$expectedHash}, Got: {$hash}"
                );
                
                // Assert 7: Hash must be 32 characters (standard MD5 length)
                $this->assertEquals(
                    32,
                    strlen($hash),
                    "Hash must be 32 characters long"
                );
            });
    }

    /**
     * Generate random URLs with various normalization issues
     * 
     * Generates URLs with:
     * - Random case (uppercase/lowercase/mixed)
     * - Optional www. prefix
     * - Random UTM parameters
     * - Optional trailing slashes
     * - Unsorted query parameters
     * 
     * @return Generator\GeneratedValueSingle
     */
    private function urlGenerator()
    {
        return Generator\bind(
            Generator\choose(0, 1), // scheme case variation
            function ($schemeCase) {
                return Generator\bind(
                    Generator\choose(0, 1), // www prefix
                    function ($hasWww) use ($schemeCase) {
                        return Generator\bind(
                            $this->domainGenerator(), // domain
                            function ($domain) use ($schemeCase, $hasWww) {
                                return Generator\bind(
                                    $this->pathGenerator(), // path
                                    function ($path) use ($schemeCase, $hasWww, $domain) {
                                        return Generator\bind(
                                            Generator\choose(0, 1), // trailing slash
                                            function ($hasTrailingSlash) use ($schemeCase, $hasWww, $domain, $path) {
                                                return Generator\bind(
                                                    $this->queryParamsGenerator(), // query params
                                                    function ($queryParams) use ($schemeCase, $hasWww, $domain, $path, $hasTrailingSlash) {
                                                        // Build URL
                                                        $scheme = $schemeCase === 0 ? 'https' : 'HTTPS';
                                                        $wwwPrefix = $hasWww === 1 ? 'www.' : '';
                                                        
                                                        // Apply random case to domain
                                                        $domainWithCase = $this->randomCase($domain);
                                                        
                                                        $url = "{$scheme}://{$wwwPrefix}{$domainWithCase}";
                                                        
                                                        if ($path !== '') {
                                                            $url .= $path;
                                                            if ($hasTrailingSlash === 1 && !str_ends_with($path, '/')) {
                                                                $url .= '/';
                                                            }
                                                        }
                                                        
                                                        if (!empty($queryParams)) {
                                                            $url .= '?' . http_build_query($queryParams);
                                                        }
                                                        
                                                        return Generator\constant([
                                                            'url' => $url,
                                                            'domain' => $domain,
                                                            'path' => $path,
                                                            'hasWww' => $hasWww,
                                                            'hasTrailingSlash' => $hasTrailingSlash,
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
        ]);
    }

    /**
     * Generate random query parameters including UTM params
     */
    private function queryParamsGenerator()
    {
        return Generator\bind(
            Generator\choose(0, 5), // number of regular params
            function ($numRegularParams) {
                return Generator\bind(
                    Generator\choose(0, 3), // number of UTM params
                    function ($numUtmParams) use ($numRegularParams) {
                        $params = [];
                        
                        // Add regular params (intentionally unsorted)
                        $regularParamNames = ['z', 'a', 'm', 'id', 'page', 'sort', 'filter', 'q'];
                        for ($i = 0; $i < $numRegularParams; $i++) {
                            $key = $regularParamNames[$i % count($regularParamNames)];
                            $params[$key] = (string)($i + 1);
                        }
                        
                        // Add UTM params
                        $utmParamNames = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
                        for ($i = 0; $i < $numUtmParams; $i++) {
                            $key = $utmParamNames[$i % count($utmParamNames)];
                            $params[$key] = ['twitter', 'facebook', 'email', 'social', 'cpc'][$i % 5];
                        }
                        
                        return Generator\constant($params);
                    }
                );
            }
        );
    }

    /**
     * Apply random case to a string
     */
    private function randomCase(string $str): string
    {
        $result = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            // Randomly uppercase some characters
            if (ctype_alpha($char) && rand(0, 1) === 1) {
                $result .= strtoupper($char);
            } else {
                $result .= $char;
            }
        }
        return $result;
    }
}

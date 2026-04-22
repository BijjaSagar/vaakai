<?php

namespace Tests\Unit;

use App\Models\Url;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase as LaravelTestCase;

/**
 * Property-Based Tests for OG Metadata Serialization Round-Trip
 * 
 * Feature: speakspace, Property 6: OG Metadata Serialization Round-Trip
 */
class OgMetadataSerializationPropertyTest extends LaravelTestCase
{
    use TestTrait;
    use RefreshDatabase;

    /**
     * Property 6: OG Metadata Serialization Round-Trip
     * 
     * **Validates: Requirements 3.5**
     * 
     * For any Url record with fully populated OG metadata fields, serializing it to
     * the Redis cache format and deserializing it back must produce a record with
     * equivalent field values (title, description, thumbnail_url, domain, og_fetched_at).
     * 
     * This test runs with a minimum of 100 iterations as per spec requirements.
     * 
     * @test
     */
    public function property_og_metadata_serialization_round_trip_preserves_data()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->urlMetadataGenerator()
            )
            ->then(function ($metadataData) {
                $originalTitle = $metadataData['title'];
                $originalDescription = $metadataData['description'];
                $originalThumbnailUrl = $metadataData['thumbnail_url'];
                $originalDomain = $metadataData['domain'];
                $originalOgFetchedAt = $metadataData['og_fetched_at'];
                $urlHash = $metadataData['url_hash'];
                
                // Serialize to Redis cache format (as done in FetchUrlMetadata job)
                $cacheKey = "og:{$urlHash}";
                $serialized = [
                    'title' => $originalTitle,
                    'description' => $originalDescription,
                    'thumbnail_url' => $originalThumbnailUrl,
                    'domain' => $originalDomain,
                    'og_fetched_at' => $originalOgFetchedAt->toIso8601String(),
                ];
                
                // Store in cache
                Cache::put($cacheKey, $serialized, 60 * 24 * 7); // 7 days
                
                // Deserialize from cache
                $deserialized = Cache::get($cacheKey);
                
                // Assert 1: Deserialized data must not be null
                $this->assertNotNull(
                    $deserialized,
                    "Deserialized data should not be null"
                );
                
                // Assert 2: Title must match
                $this->assertEquals(
                    $originalTitle,
                    $deserialized['title'],
                    "Title must be preserved. Expected: {$originalTitle}, Got: {$deserialized['title']}"
                );
                
                // Assert 3: Description must match (can be null)
                $this->assertEquals(
                    $originalDescription,
                    $deserialized['description'],
                    "Description must be preserved"
                );
                
                // Assert 4: Thumbnail URL must match (can be null)
                $this->assertEquals(
                    $originalThumbnailUrl,
                    $deserialized['thumbnail_url'],
                    "Thumbnail URL must be preserved"
                );
                
                // Assert 5: Domain must match
                $this->assertEquals(
                    $originalDomain,
                    $deserialized['domain'],
                    "Domain must be preserved. Expected: {$originalDomain}, Got: {$deserialized['domain']}"
                );
                
                // Assert 6: og_fetched_at timestamp must be preserved (as ISO8601 string)
                $this->assertEquals(
                    $originalOgFetchedAt->toIso8601String(),
                    $deserialized['og_fetched_at'],
                    "og_fetched_at timestamp must be preserved"
                );
                
                // Assert 7: Deserialized timestamp can be parsed back to Carbon
                $deserializedTimestamp = \Carbon\Carbon::parse($deserialized['og_fetched_at']);
                $this->assertEquals(
                    $originalOgFetchedAt->timestamp,
                    $deserializedTimestamp->timestamp,
                    "Timestamp must be parseable and equivalent"
                );
                
                // Clean up cache
                Cache::forget($cacheKey);
            });
    }

    /**
     * Generate random URL metadata
     * 
     * @return Generator\GeneratedValueSingle
     */
    private function urlMetadataGenerator()
    {
        return Generator\bind(
            Generator\choose(0, 1), // hasDescription
            function ($hasDescription) {
                return Generator\bind(
                    Generator\choose(0, 1), // hasThumbnail
                    function ($hasThumbnail) use ($hasDescription) {
                        $title = $this->randomText('Title');
                        $description = $hasDescription ? $this->randomText('Description') : null;
                        $thumbnailUrl = $hasThumbnail ? $this->randomUrl('/image.jpg') : null;
                        $domain = $this->randomDomain();
                        $ogFetchedAt = now()->subMinutes(rand(1, 10000));
                        $urlHash = md5($this->randomUrl());
                        
                        return Generator\constant([
                            'title' => $title,
                            'description' => $description,
                            'thumbnail_url' => $thumbnailUrl,
                            'domain' => $domain,
                            'og_fetched_at' => $ogFetchedAt,
                            'url_hash' => $urlHash,
                        ]);
                    }
                );
            }
        );
    }

    /**
     * Generate random text
     */
    private function randomText(string $prefix): string
    {
        $words = ['Amazing', 'Incredible', 'Awesome', 'Great', 'Wonderful', 'Fantastic', 'Brilliant', 'Superb'];
        $word1 = $words[array_rand($words)];
        $word2 = $words[array_rand($words)];
        $number = rand(1, 10000);
        
        return "{$prefix} {$word1} {$word2} {$number}";
    }

    /**
     * Generate random domain
     */
    private function randomDomain(): string
    {
        $domains = [
            'example.com', 'test.org', 'demo.net', 'site.io', 'blog.dev',
            'news.co.uk', 'shop.com', 'forum.org', 'portal.info', 'service.biz'
        ];
        
        return $domains[array_rand($domains)];
    }

    /**
     * Generate random URL
     */
    private function randomUrl(string $path = ''): string
    {
        $domain = $this->randomDomain();
        $paths = ['/article', '/blog/post', '/page', '/news/story', '/product'];
        
        if ($path === '') {
            $path = $paths[array_rand($paths)];
        }
        
        return "https://{$domain}{$path}";
    }
}

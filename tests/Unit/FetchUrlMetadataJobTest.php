<?php

namespace Tests\Unit;

use App\Jobs\FetchUrlMetadata;
use App\Models\Url;
use App\Services\OgScraperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FetchUrlMetadataJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_fetches_metadata_and_updates_url_record()
    {
        // Create a URL record
        $url = Url::create([
            'url_hash' => md5('https://example.com/article'),
            'original_url' => 'https://example.com/article',
            'normalized_url' => 'https://example.com/article',
            'slug' => 'abcd1234',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);
        
        // Mock the OgScraperService
        $mockScraper = $this->createMock(OgScraperService::class);
        $mockScraper->expects($this->once())
            ->method('fetch')
            ->with('https://example.com/article')
            ->willReturn([
                'title' => 'Test Article Title',
                'description' => 'Test description',
                'thumbnail_url' => 'https://example.com/image.jpg',
                'domain' => 'Example Site',
                'og_fetched_at' => now(),
            ]);
        
        // Execute the job
        $job = new FetchUrlMetadata($url);
        $job->handle($mockScraper);
        
        // Refresh the URL model from database
        $url->refresh();
        
        // Assert URL record was updated
        $this->assertEquals('Test Article Title', $url->title);
        $this->assertEquals('Test description', $url->description);
        $this->assertEquals('https://example.com/image.jpg', $url->thumbnail_url);
        $this->assertEquals('Example Site', $url->domain);
        $this->assertNotNull($url->og_fetched_at);
    }
    
    public function test_job_caches_metadata_in_redis()
    {
        // Create a URL record
        $url = Url::create([
            'url_hash' => md5('https://example.com/article'),
            'original_url' => 'https://example.com/article',
            'normalized_url' => 'https://example.com/article',
            'slug' => 'abcd1234',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);
        
        $fetchedAt = now();
        
        // Mock the OgScraperService
        $mockScraper = $this->createMock(OgScraperService::class);
        $mockScraper->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'title' => 'Cached Title',
                'description' => 'Cached description',
                'thumbnail_url' => 'https://example.com/cached.jpg',
                'domain' => 'Cached Site',
                'og_fetched_at' => $fetchedAt,
            ]);
        
        // Execute the job
        $job = new FetchUrlMetadata($url);
        $job->handle($mockScraper);
        
        // Assert data was cached
        $cacheKey = "og:{$url->url_hash}";
        $cached = Cache::get($cacheKey);
        
        $this->assertNotNull($cached);
        $this->assertEquals('Cached Title', $cached['title']);
        $this->assertEquals('Cached description', $cached['description']);
        $this->assertEquals('https://example.com/cached.jpg', $cached['thumbnail_url']);
        $this->assertEquals('Cached Site', $cached['domain']);
        $this->assertEquals($fetchedAt->toIso8601String(), $cached['og_fetched_at']);
    }
    
    public function test_job_handles_scraper_failure_gracefully()
    {
        // Create a URL record
        $url = Url::create([
            'url_hash' => md5('https://example.com/article'),
            'original_url' => 'https://example.com/article',
            'normalized_url' => 'https://example.com/article',
            'slug' => 'abcd1234',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);
        
        // Mock the OgScraperService to return fallback data
        $mockScraper = $this->createMock(OgScraperService::class);
        $mockScraper->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'title' => 'example.com',
                'description' => null,
                'thumbnail_url' => null,
                'domain' => 'example.com',
                'og_fetched_at' => now(),
            ]);
        
        // Execute the job
        $job = new FetchUrlMetadata($url);
        $job->handle($mockScraper);
        
        // Refresh the URL model
        $url->refresh();
        
        // Assert fallback data was stored
        $this->assertEquals('example.com', $url->title);
        $this->assertNull($url->description);
        $this->assertNull($url->thumbnail_url);
        $this->assertEquals('example.com', $url->domain);
        $this->assertNotNull($url->og_fetched_at);
    }
    
    public function test_job_stores_null_values_for_missing_optional_fields()
    {
        // Create a URL record
        $url = Url::create([
            'url_hash' => md5('https://example.com/article'),
            'original_url' => 'https://example.com/article',
            'normalized_url' => 'https://example.com/article',
            'slug' => 'abcd1234',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);
        
        // Mock the OgScraperService with partial data
        $mockScraper = $this->createMock(OgScraperService::class);
        $mockScraper->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'title' => 'Title Only',
                'description' => null,
                'thumbnail_url' => null,
                'domain' => 'example.com',
                'og_fetched_at' => now(),
            ]);
        
        // Execute the job
        $job = new FetchUrlMetadata($url);
        $job->handle($mockScraper);
        
        // Refresh the URL model
        $url->refresh();
        
        // Assert optional fields are null
        $this->assertEquals('Title Only', $url->title);
        $this->assertNull($url->description);
        $this->assertNull($url->thumbnail_url);
    }
}

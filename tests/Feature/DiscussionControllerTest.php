<?php

namespace Tests\Feature;

use App\Jobs\FetchUrlMetadata;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DiscussionControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_validates_url_is_required()
    {
        $response = $this->postJson('/discuss', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    /** @test */
    public function it_rejects_url_without_http_or_https_prefix()
    {
        $response = $this->postJson('/discuss', [
            'url' => 'example.com/article',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
        
        $this->assertEquals(0, Url::count());
    }

    /** @test */
    public function it_accepts_url_with_http_prefix()
    {
        $response = $this->postJson('/discuss', [
            'url' => 'http://example.com/article',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'redirect' => '/d/' . Url::first()->slug,
            ]);
        
        $this->assertEquals(1, Url::count());
    }

    /** @test */
    public function it_accepts_url_with_https_prefix()
    {
        $response = $this->postJson('/discuss', [
            'url' => 'https://example.com/article',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'redirect' => '/d/' . Url::first()->slug,
            ]);
        
        $this->assertEquals(1, Url::count());
    }

    /** @test */
    public function it_creates_new_url_record_for_first_submission()
    {
        $response = $this->postJson('/discuss', [
            'url' => 'https://example.com/article?utm_source=twitter&b=2&a=1',
        ]);

        $response->assertStatus(201);
        
        $url = Url::first();
        $this->assertNotNull($url);
        $this->assertEquals('https://example.com/article?utm_source=twitter&b=2&a=1', $url->original_url);
        $this->assertEquals('https://example.com/article?a=1&b=2', $url->normalized_url);
        $this->assertEquals(md5('https://example.com/article?a=1&b=2'), $url->url_hash);
        $this->assertEquals('example.com', $url->domain);
        $this->assertEquals(0, $url->comment_count);
        $this->assertNotNull($url->slug);
        $this->assertEquals(8, strlen($url->slug));
    }

    /** @test */
    public function it_redirects_to_existing_discussion_for_duplicate_url()
    {
        // First submission
        $firstResponse = $this->postJson('/discuss', [
            'url' => 'https://example.com/article',
        ]);

        $firstResponse->assertStatus(201);
        $firstUrl = Url::first();
        
        // Second submission with same URL
        $secondResponse = $this->postJson('/discuss', [
            'url' => 'https://example.com/article',
        ]);

        $secondResponse->assertStatus(200)
            ->assertJson([
                'redirect' => "/d/{$firstUrl->slug}",
            ]);
        
        // Should still only have one URL record
        $this->assertEquals(1, Url::count());
    }

    /** @test */
    public function it_deduplicates_urls_with_different_variations()
    {
        // First submission
        $firstResponse = $this->postJson('/discuss', [
            'url' => 'https://www.example.com/article/?utm_source=twitter&b=2&a=1/',
        ]);

        $firstResponse->assertStatus(201);
        $firstUrl = Url::first();
        
        // Second submission with normalized equivalent
        $secondResponse = $this->postJson('/discuss', [
            'url' => 'https://example.com/article?a=1&b=2',
        ]);

        $secondResponse->assertStatus(200)
            ->assertJson([
                'redirect' => "/d/{$firstUrl->slug}",
            ]);
        
        // Should still only have one URL record
        $this->assertEquals(1, Url::count());
    }

    /** @test */
    public function it_generates_slug_from_first_8_chars_of_hash()
    {
        $response = $this->postJson('/discuss', [
            'url' => 'https://example.com/article',
        ]);

        $response->assertStatus(201);
        
        $url = Url::first();
        $expectedSlug = substr(md5('https://example.com/article'), 0, 8);
        
        $this->assertEquals($expectedSlug, $url->slug);
    }

    /** @test */
    public function it_generates_next_8_char_window_when_slug_collides()
    {
        $normalizedUrl = 'https://example.com/article';
        $hash = md5($normalizedUrl);
        $firstSlug = substr($hash, 0, 8);
        
        // Create a URL with the first 8-char slug already taken
        Url::create([
            'url_hash' => 'different_hash_123456789012',
            'original_url' => 'https://different.com',
            'normalized_url' => 'https://different.com',
            'slug' => $firstSlug,
            'domain' => 'different.com',
            'comment_count' => 0,
        ]);
        
        // Submit URL that would generate the same first slug
        $response = $this->postJson('/discuss', [
            'url' => $normalizedUrl,
        ]);

        $response->assertStatus(201);
        
        $url = Url::where('url_hash', $hash)->first();
        $expectedSecondSlug = substr($hash, 8, 8);
        
        $this->assertEquals($expectedSecondSlug, $url->slug);
    }

    /** @test */
    public function it_tries_up_to_4_slug_windows()
    {
        $normalizedUrl = 'https://example.com/article';
        $hash = md5($normalizedUrl);
        
        // Create URLs with first 3 possible slugs taken
        for ($i = 0; $i < 3; $i++) {
            $slug = substr($hash, $i * 8, 8);
            Url::create([
                'url_hash' => "different_hash_{$i}_" . str_repeat('0', 10),
                'original_url' => "https://different{$i}.com",
                'normalized_url' => "https://different{$i}.com",
                'slug' => $slug,
                'domain' => "different{$i}.com",
                'comment_count' => 0,
            ]);
        }
        
        // Submit URL that would need the 4th window
        $response = $this->postJson('/discuss', [
            'url' => $normalizedUrl,
        ]);

        $response->assertStatus(201);
        
        $url = Url::where('url_hash', $hash)->first();
        $expectedFourthSlug = substr($hash, 24, 8);
        
        $this->assertEquals($expectedFourthSlug, $url->slug);
    }

    /** @test */
    public function it_generates_fallback_slug_when_all_windows_are_taken()
    {
        $normalizedUrl = 'https://example.com/article';
        $hash = md5($normalizedUrl);
        
        // Create URLs with all 4 possible slugs taken
        for ($i = 0; $i < 4; $i++) {
            $slug = substr($hash, $i * 8, 8);
            Url::create([
                'url_hash' => "different_hash_{$i}_" . str_repeat('0', 10),
                'original_url' => "https://different{$i}.com",
                'normalized_url' => "https://different{$i}.com",
                'slug' => $slug,
                'domain' => "different{$i}.com",
                'comment_count' => 0,
            ]);
        }
        
        // Submit URL that would need fallback
        $response = $this->postJson('/discuss', [
            'url' => $normalizedUrl,
        ]);

        $response->assertStatus(201);
        
        $url = Url::where('url_hash', $hash)->first();
        
        // Should have a slug that starts with first 8 chars and has 4 additional random chars
        $this->assertStringStartsWith(substr($hash, 0, 8), $url->slug);
        $this->assertEquals(12, strlen($url->slug));
    }

    /** @test */
    public function it_stores_domain_correctly()
    {
        $response = $this->postJson('/discuss', [
            'url' => 'https://www.example.com/article',
        ]);

        $response->assertStatus(201);
        
        $url = Url::first();
        // Domain is parsed from normalized URL (www. is removed during normalization)
        $this->assertEquals('example.com', $url->domain);
    }

    /** @test */
    public function it_handles_urls_with_ports()
    {
        $response = $this->postJson('/discuss', [
            'url' => 'https://example.com:8080/article',
        ]);

        $response->assertStatus(201);
        
        $url = Url::first();
        $this->assertEquals('example.com', $url->domain);
        $this->assertEquals('https://example.com:8080/article', $url->normalized_url);
    }

    /** @test */
    public function it_handles_urls_with_fragments()
    {
        $response = $this->postJson('/discuss', [
            'url' => 'https://example.com/article#section',
        ]);

        $response->assertStatus(201);
        
        $url = Url::first();
        $this->assertEquals('https://example.com/article#section', $url->normalized_url);
    }

    /** @test */
    public function it_enforces_rate_limit_of_5_submissions_per_minute()
    {
        // Make 5 successful submissions (the limit)
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/discuss', [
                'url' => "https://example.com/article{$i}",
            ]);

            $response->assertStatus(201);
        }
        
        // 6th submission should be rate limited
        $response = $this->postJson('/discuss', [
            'url' => 'https://example.com/article6',
        ]);

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        
        // Should still only have 5 URL records
        $this->assertEquals(5, Url::count());
    }

    /** @test */
    public function it_dispatches_fetch_url_metadata_job_for_new_url()
    {
        Queue::fake();

        $response = $this->postJson('/discuss', [
            'url' => 'https://example.com/article',
        ]);

        $response->assertStatus(201);

        // Assert the job was dispatched
        Queue::assertPushed(FetchUrlMetadata::class, function ($job) {
            return $job->url->normalized_url === 'https://example.com/article';
        });
    }

    /** @test */
    public function it_does_not_dispatch_job_for_duplicate_url()
    {
        Queue::fake();

        // First submission
        $this->postJson('/discuss', [
            'url' => 'https://example.com/article',
        ]);

        Queue::assertPushed(FetchUrlMetadata::class, 1);

        // Second submission (duplicate)
        $this->postJson('/discuss', [
            'url' => 'https://example.com/article',
        ]);

        // Should still only have dispatched once
        Queue::assertPushed(FetchUrlMetadata::class, 1);
    }
}

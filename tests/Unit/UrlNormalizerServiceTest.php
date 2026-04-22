<?php

namespace Tests\Unit;

use App\Services\UrlNormalizerService;
use PHPUnit\Framework\TestCase;

class UrlNormalizerServiceTest extends TestCase
{
    private UrlNormalizerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UrlNormalizerService();
    }

    /** @test */
    public function it_lowercases_the_entire_url()
    {
        $url = 'HTTPS://EXAMPLE.COM/PATH';
        $normalized = $this->service->normalize($url);
        
        $this->assertEquals('https://example.com/path', $normalized);
    }

    /** @test */
    public function it_removes_www_prefix_from_host()
    {
        $url = 'https://www.example.com/article';
        $normalized = $this->service->normalize($url);
        
        $this->assertEquals('https://example.com/article', $normalized);
    }

    /** @test */
    public function it_strips_utm_parameters()
    {
        $url = 'https://example.com/article?utm_source=twitter&utm_medium=social&id=123';
        $normalized = $this->service->normalize($url);
        
        $this->assertEquals('https://example.com/article?id=123', $normalized);
    }

    /** @test */
    public function it_removes_trailing_slash_from_path()
    {
        $url = 'https://example.com/article/';
        $normalized = $this->service->normalize($url);
        
        $this->assertEquals('https://example.com/article', $normalized);
    }

    /** @test */
    public function it_sorts_query_parameters_alphabetically()
    {
        $url = 'https://example.com/article?z=3&a=1&m=2';
        $normalized = $this->service->normalize($url);
        
        $this->assertEquals('https://example.com/article?a=1&m=2&z=3', $normalized);
    }

    /** @test */
    public function it_applies_all_normalization_rules_together()
    {
        $url = 'https://WWW.Example.com/article/?utm_source=twitter&b=2&a=1';
        $normalized = $this->service->normalize($url);
        
        $this->assertEquals('https://example.com/article?a=1&b=2', $normalized);
    }

    /** @test */
    public function it_handles_urls_without_query_parameters()
    {
        $url = 'https://example.com/article';
        $normalized = $this->service->normalize($url);
        
        $this->assertEquals('https://example.com/article', $normalized);
    }

    /** @test */
    public function it_handles_urls_with_port_numbers()
    {
        $url = 'https://example.com:8080/article';
        $normalized = $this->service->normalize($url);
        
        $this->assertEquals('https://example.com:8080/article', $normalized);
    }

    /** @test */
    public function it_handles_urls_with_fragments()
    {
        $url = 'https://example.com/article#section';
        $normalized = $this->service->normalize($url);
        
        $this->assertEquals('https://example.com/article#section', $normalized);
    }

    /** @test */
    public function it_generates_md5_hash_of_normalized_url()
    {
        $normalizedUrl = 'https://example.com/article?a=1&b=2';
        $hash = $this->service->hash($normalizedUrl);
        
        $this->assertEquals(md5($normalizedUrl), $hash);
        $this->assertEquals(32, strlen($hash));
    }

    /** @test */
    public function it_produces_consistent_hash_for_same_normalized_url()
    {
        $normalizedUrl = 'https://example.com/article';
        $hash1 = $this->service->hash($normalizedUrl);
        $hash2 = $this->service->hash($normalizedUrl);
        
        $this->assertEquals($hash1, $hash2);
    }
}

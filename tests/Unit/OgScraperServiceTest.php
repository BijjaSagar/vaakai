<?php

namespace Tests\Unit;

use App\Services\OgScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Tests\TestCase;

class OgScraperServiceTest extends TestCase
{
    public function test_fetch_extracts_og_metadata_successfully()
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="Test Article Title" />
    <meta property="og:description" content="This is a test description" />
    <meta property="og:image" content="https://example.com/image.jpg" />
    <meta property="og:site_name" content="Example Site" />
    <title>Fallback Title</title>
</head>
<body>Content</body>
</html>
HTML;

        $service = new OgScraperService();
        
        // We'll use reflection to test the extractMetadata method directly
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractMetadata');
        $method->setAccessible(true);
        
        $result = $method->invoke($service, $html, 'example.com');
        
        $this->assertEquals('Test Article Title', $result['title']);
        $this->assertEquals('This is a test description', $result['description']);
        $this->assertEquals('https://example.com/image.jpg', $result['thumbnail_url']);
        $this->assertEquals('Example Site', $result['domain']);
    }
    
    public function test_fetch_falls_back_to_title_tag_when_og_title_absent()
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Fallback Title from Title Tag</title>
    <meta property="og:description" content="Description present" />
</head>
<body>Content</body>
</html>
HTML;

        $service = new OgScraperService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractMetadata');
        $method->setAccessible(true);
        
        $result = $method->invoke($service, $html, 'example.com');
        
        $this->assertEquals('Fallback Title from Title Tag', $result['title']);
        $this->assertEquals('Description present', $result['description']);
    }
    
    public function test_fetch_falls_back_to_domain_when_no_title_found()
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta property="og:description" content="Description only" />
</head>
<body>Content</body>
</html>
HTML;

        $service = new OgScraperService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractMetadata');
        $method->setAccessible(true);
        
        $result = $method->invoke($service, $html, 'example.com');
        
        $this->assertEquals('example.com', $result['title']);
        $this->assertEquals('example.com', $result['domain']);
    }
    
    public function test_fetch_falls_back_to_domain_when_og_site_name_absent()
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="Test Title" />
</head>
<body>Content</body>
</html>
HTML;

        $service = new OgScraperService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractMetadata');
        $method->setAccessible(true);
        
        $result = $method->invoke($service, $html, 'example.com');
        
        $this->assertEquals('Test Title', $result['title']);
        $this->assertEquals('example.com', $result['domain']);
    }
    
    public function test_fetch_returns_null_for_missing_optional_fields()
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Simple Title</title>
</head>
<body>Content</body>
</html>
HTML;

        $service = new OgScraperService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractMetadata');
        $method->setAccessible(true);
        
        $result = $method->invoke($service, $html, 'example.com');
        
        $this->assertEquals('Simple Title', $result['title']);
        $this->assertNull($result['description']);
        $this->assertNull($result['thumbnail_url']);
    }
    
    public function test_fetch_returns_domain_fallback_on_exception()
    {
        $service = new OgScraperService();
        
        // Test with an invalid URL that will cause an exception
        $result = $service->fetch('http://this-domain-does-not-exist-12345.com');
        
        $this->assertEquals('this-domain-does-not-exist-12345.com', $result['title']);
        $this->assertEquals('this-domain-does-not-exist-12345.com', $result['domain']);
        $this->assertNull($result['description']);
        $this->assertNull($result['thumbnail_url']);
        $this->assertNotNull($result['og_fetched_at']);
    }
    
    public function test_fetch_handles_malformed_html_gracefully()
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="Test Title
    <meta property="og:description" content="Broken HTML">
</head>
<body>Content
HTML;

        $service = new OgScraperService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractMetadata');
        $method->setAccessible(true);
        
        // Should not throw an exception even with malformed HTML
        $result = $method->invoke($service, $html, 'example.com');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('domain', $result);
    }
}

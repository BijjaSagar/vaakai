<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use DOMDocument;
use DOMXPath;

class OgScraperService
{
    /**
     * Fetch OpenGraph metadata from a URL.
     * 
     * Steps:
     * 1. Fetch HTML content using Guzzle with 8s timeout, following redirects, Googlebot User-Agent
     * 2. Extract og:title, og:description, og:image, og:site_name via DOMDocument
     * 3. Fallback to <title> tag for title, parse_url for domain/site_name
     * 4. On exception: return domain fallback array
     * 
     * @param string $url
     * @return array ['title', 'description', 'thumbnail_url', 'domain', 'og_fetched_at']
     */
    public function fetch(string $url): array
    {
        $domain = parse_url($url, PHP_URL_HOST) ?? 'Unknown';
        $now = now();
        
        // Default fallback response
        $fallback = [
            'title' => $domain,
            'description' => null,
            'thumbnail_url' => null,
            'domain' => $domain,
            'og_fetched_at' => $now,
        ];
        
        try {
            // Step 1: Fetch HTML with Guzzle
            $client = new Client();
            $response = $client->get($url, [
                'timeout' => 8,
                'allow_redirects' => true,
                'headers' => [
                    'User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
                ],
            ]);
            
            $html = (string) $response->getBody();
            
            // Step 2: Parse HTML and extract metadata
            $metadata = $this->extractMetadata($html, $domain);
            $metadata['og_fetched_at'] = $now;
            
            return $metadata;
            
        } catch (GuzzleException $e) {
            // Step 4: On exception, return domain fallback
            return $fallback;
        } catch (\Exception $e) {
            // Catch any other exceptions (DOMDocument parsing errors, etc.)
            return $fallback;
        }
    }
    
    /**
     * Extract OpenGraph metadata from HTML content.
     * 
     * @param string $html
     * @param string $domain
     * @return array ['title', 'description', 'thumbnail_url', 'domain']
     */
    private function extractMetadata(string $html, string $domain): array
    {
        // Suppress DOMDocument warnings for malformed HTML
        libxml_use_internal_errors(true);
        
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        
        libxml_clear_errors();
        
        $xpath = new DOMXPath($doc);
        
        // Extract OpenGraph meta tags
        $ogTitle = $this->getMetaContent($xpath, 'og:title');
        $ogDescription = $this->getMetaContent($xpath, 'og:description');
        $ogImage = $this->getMetaContent($xpath, 'og:image');
        $ogSiteName = $this->getMetaContent($xpath, 'og:site_name');
        
        // Fallback for title: use <title> tag if og:title is absent
        $title = $ogTitle;
        if (empty($title)) {
            $titleNodes = $xpath->query('//title');
            if ($titleNodes->length > 0) {
                $title = trim($titleNodes->item(0)->textContent);
            }
        }
        
        // Final fallback for title: use domain
        if (empty($title)) {
            $title = $domain;
        }
        
        // Fallback for site_name: use domain if og:site_name is absent
        $siteName = $ogSiteName ?: $domain;
        
        return [
            'title' => $title,
            'description' => $ogDescription,
            'thumbnail_url' => $ogImage,
            'domain' => $siteName,
        ];
    }
    
    /**
     * Get content attribute from a meta tag by property name.
     * 
     * @param DOMXPath $xpath
     * @param string $property
     * @return string|null
     */
    private function getMetaContent(DOMXPath $xpath, string $property): ?string
    {
        // Try property attribute first (OpenGraph standard)
        $nodes = $xpath->query("//meta[@property='$property']");
        
        if ($nodes->length > 0) {
            $content = $nodes->item(0)->getAttribute('content');
            return !empty($content) ? trim($content) : null;
        }
        
        // Try name attribute as fallback (some sites use this)
        $nodes = $xpath->query("//meta[@name='$property']");
        
        if ($nodes->length > 0) {
            $content = $nodes->item(0)->getAttribute('content');
            return !empty($content) ? trim($content) : null;
        }
        
        return null;
    }
}

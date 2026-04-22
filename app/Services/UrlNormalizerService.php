<?php

namespace App\Services;

class UrlNormalizerService
{
    /**
     * Normalize a URL to its canonical form.
     * 
     * Steps:
     * 1. Lowercase entire URL
     * 2. Remove www. prefix from host
     * 3. Strip UTM parameters (utm_source, utm_medium, utm_campaign, utm_term, utm_content)
     * 4. Remove trailing slash from path
     * 5. Sort remaining query parameters alphabetically
     * 
     * @param string $url
     * @return string Canonical URL
     */
    public function normalize(string $url): string
    {
        // Step 1: Lowercase entire URL
        $url = strtolower($url);
        
        // Step 4: Remove trailing slash from path (before parsing)
        // This prevents the trailing slash from being included in query params
        $url = preg_replace('#/(\?|$)#', '$1', $url);
        
        // Parse the URL into components
        $parsed = parse_url($url);
        
        if ($parsed === false) {
            return $url;
        }
        
        // Step 2: Remove www. prefix from host
        $host = $parsed['host'] ?? '';
        $host = preg_replace('/^www\./', '', $host);
        
        // Step 3 & 5: Parse query string, strip UTM params, sort alphabetically
        $queryParams = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            
            // Remove UTM parameters
            $queryParams = array_filter($queryParams, function ($key) {
                return !preg_match('/^utm_/', $key);
            }, ARRAY_FILTER_USE_KEY);
            
            // Sort alphabetically by key
            ksort($queryParams);
        }
        
        // Get path and remove any remaining trailing slashes
        $path = $parsed['path'] ?? '';
        $path = rtrim($path, '/');
        
        // Rebuild the URL
        $normalized = ($parsed['scheme'] ?? 'https') . '://';
        $normalized .= $host;
        
        if (isset($parsed['port'])) {
            $normalized .= ':' . $parsed['port'];
        }
        
        $normalized .= $path;
        
        if (!empty($queryParams)) {
            $normalized .= '?' . http_build_query($queryParams);
        }
        
        if (isset($parsed['fragment'])) {
            $normalized .= '#' . $parsed['fragment'];
        }
        
        return $normalized;
    }
    
    /**
     * Generate MD5 hash of a normalized URL.
     * 
     * @param string $normalizedUrl
     * @return string MD5 hash (32 hex characters)
     */
    public function hash(string $normalizedUrl): string
    {
        return md5($normalizedUrl);
    }
}

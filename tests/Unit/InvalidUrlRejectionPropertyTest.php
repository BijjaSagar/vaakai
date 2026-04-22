<?php

namespace Tests\Unit;

use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase as LaravelTestCase;

/**
 * Property-Based Tests for Invalid URL Rejection
 * 
 * Feature: speakspace, Property 2: Invalid URL Rejection
 */
class InvalidUrlRejectionPropertyTest extends LaravelTestCase
{
    use TestTrait;
    use RefreshDatabase;

    /**
     * Property 2: Invalid URL Rejection
     * 
     * **Validates: Requirements 1.4**
     * 
     * For any string that is empty or does not begin with http:// or https://,
     * submitting it to POST /discuss must return a validation error and must not
     * create any new record in the urls table.
     * 
     * This test runs with a minimum of 100 iterations as per spec requirements.
     * Eris default configuration runs 100 iterations by default.
     * 
     * @test
     */
    public function property_invalid_urls_are_rejected_with_422_and_no_database_record()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->invalidUrlGenerator()
            )
            ->then(function ($invalidUrl) {
                // Count urls records before submission
                $urlsCountBefore = DB::table('urls')->count();
                
                // Submit the invalid URL to POST /discuss
                $response = $this->postJson('/discuss', [
                    'url' => $invalidUrl,
                ]);
                
                // Assert 1: Response must be 422 (validation error)
                $response->assertStatus(422);
                
                // Assert 2: Response must contain validation errors
                $response->assertJsonValidationErrors('url');
                
                // Assert 3: No new urls record must be created
                $urlsCountAfter = DB::table('urls')->count();
                $this->assertEquals(
                    $urlsCountBefore,
                    $urlsCountAfter,
                    "No new urls record should be created for invalid URL: {$invalidUrl}"
                );
            });
    }

    /**
     * Generate random invalid URLs
     * 
     * Generates strings that are either:
     * - Empty strings
     * - Strings without http:// or https:// prefix
     * 
     * @return Generator\GeneratedValueSingle
     */
    private function invalidUrlGenerator()
    {
        return Generator\oneOf(
            // Empty string
            Generator\constant(''),
            
            // Strings without http/https prefix - just domain-like strings
            Generator\map(
                function ($domain) {
                    return $domain;
                },
                $this->domainLikeStringGenerator()
            ),
            
            // Strings with invalid protocols
            Generator\map(
                function ($data) {
                    return $data['protocol'] . '://' . $data['domain'];
                },
                Generator\bind(
                    $this->invalidProtocolGenerator(),
                    function ($protocol) {
                        return Generator\bind(
                            $this->domainLikeStringGenerator(),
                            function ($domain) use ($protocol) {
                                return Generator\constant([
                                    'protocol' => $protocol,
                                    'domain' => $domain,
                                ]);
                            }
                        );
                    }
                )
            ),
            
            // Random strings that don't look like URLs
            Generator\elements([
                'not a url',
                'just some text',
                'example.com',
                'www.example.com',
                'ftp://example.com',
                'file:///path/to/file',
                '//example.com',
                'javascript:alert(1)',
                'data:text/html,<h1>test</h1>',
                'mailto:test@example.com',
                'tel:+1234567890',
                ' ',
                '   ',
                'ht tp://example.com',
                'http//example.com',
                'http:/example.com',
                'http:example.com',
            ])
        );
    }

    /**
     * Generate domain-like strings without protocol
     */
    private function domainLikeStringGenerator()
    {
        return Generator\elements([
            'example.com',
            'test.org',
            'demo.net',
            'www.example.com',
            'subdomain.example.com',
            'site.io',
            'blog.dev',
            'news.co.uk',
            'shop.com/path',
            'forum.org/article?id=123',
        ]);
    }

    /**
     * Generate invalid protocol strings
     */
    private function invalidProtocolGenerator()
    {
        return Generator\elements([
            'ftp',
            'file',
            'javascript',
            'data',
            'mailto',
            'tel',
            'ssh',
            'git',
            'htp',  // typo
            'htps', // typo
            'http ', // with space
            'https ', // with space
        ]);
    }
}

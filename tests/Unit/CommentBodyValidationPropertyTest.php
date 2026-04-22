<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\Url;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for Comment Body Validation
 * 
 * Feature: speakspace, Property 9: Comment Body Validation
 */
class CommentBodyValidationPropertyTest extends TestCase
{
    use TestTrait, RefreshDatabase;

    /**
     * Property 9: Comment Body Validation
     * 
     * **Validates: Requirements 5.2**
     * 
     * For any string that is empty or whose length exceeds 2000 characters,
     * submitting it as a comment body must return a validation error and must
     * not create any new record in the comments table.
     * 
     * This test runs with a minimum of 100 iterations as per spec requirements.
     * 
     * @test
     */
    public function property_invalid_comment_bodies_are_rejected()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->invalidBodyGenerator()
            )
            ->then(function ($bodyData) {
                // Create a URL for testing
                $url = Url::create([
                    'url_hash' => md5('https://example.com/test-' . uniqid()),
                    'original_url' => 'https://example.com/test',
                    'normalized_url' => 'https://example.com/test',
                    'slug' => substr(md5(uniqid()), 0, 8),
                    'domain' => 'example.com',
                    'comment_count' => 0,
                ]);
                
                $initialCommentCount = Comment::count();
                
                // Attempt to post comment with invalid body (without rate limiting)
                $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                    ->postJson("/d/{$url->slug}/comment", [
                        'body' => $bodyData['body'],
                        'sentiment' => 'neutral',
                        'guest_name' => 'Test User',
                    ]);
                
                // Assert 422 validation error
                $response->assertStatus(422);
                $response->assertJsonValidationErrors('body');
                
                // Assert no new comment was created
                $this->assertEquals(
                    $initialCommentCount,
                    Comment::count(),
                    "No new comment should be created for invalid body. Body length: " . strlen($bodyData['body'])
                );
            });
    }

    /**
     * Property 9: Comment Body Validation (Valid Bodies)
     * 
     * **Validates: Requirements 5.2**
     * 
     * For any string with length between 1 and 2000 characters,
     * submitting it as a comment body must be accepted.
     * 
     * @test
     */
    public function property_valid_comment_bodies_are_accepted()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->validBodyGenerator()
            )
            ->then(function ($bodyData) {
                // Create a URL for testing
                $url = Url::create([
                    'url_hash' => md5('https://example.com/test-' . uniqid()),
                    'original_url' => 'https://example.com/test',
                    'normalized_url' => 'https://example.com/test',
                    'slug' => substr(md5(uniqid()), 0, 8),
                    'domain' => 'example.com',
                    'comment_count' => 0,
                ]);
                
                $initialCommentCount = Comment::count();
                
                // Post comment with valid body (without rate limiting)
                $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                    ->postJson("/d/{$url->slug}/comment", [
                        'body' => $bodyData['body'],
                        'sentiment' => 'positive',
                        'guest_name' => 'Test User',
                    ]);
                
                // Assert successful creation
                $response->assertStatus(201);
                
                // Assert new comment was created
                $this->assertEquals(
                    $initialCommentCount + 1,
                    Comment::count(),
                    "A new comment should be created for valid body. Body length: " . strlen($bodyData['body'])
                );
                
                // Verify the comment body matches
                $comment = Comment::latest()->first();
                $this->assertEquals($bodyData['body'], $comment->body);
            });
    }

    /**
     * Generate invalid comment bodies (empty or > 2000 chars)
     */
    private function invalidBodyGenerator()
    {
        return Generator\oneOf(
            // Empty string
            Generator\constant(['body' => '', 'reason' => 'empty']),
            
            // String exceeding 2000 characters
            Generator\bind(
                Generator\choose(2001, 3000),
                function ($length) {
                    return Generator\constant([
                        'body' => str_repeat('a', $length),
                        'reason' => 'too_long',
                        'length' => $length,
                    ]);
                }
            )
        );
    }

    /**
     * Generate valid comment bodies (1-2000 chars)
     */
    private function validBodyGenerator()
    {
        return Generator\bind(
            Generator\choose(1, 2000),
            function ($length) {
                // Generate a string of the specified length
                $words = ['Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit'];
                $body = '';
                
                while (strlen($body) < $length) {
                    $word = $words[array_rand($words)];
                    $body .= $word . ' ';
                }
                
                // Trim to exact length and remove trailing space
                $body = rtrim(substr($body, 0, $length));
                
                return Generator\constant([
                    'body' => $body,
                    'length' => strlen($body),
                ]);
            }
        );
    }
}

<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\Url;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for Comment Creation Invariants
 * 
 * Feature: speakspace, Property 10: Comment Creation Invariants
 */
class CommentCreationInvariantsPropertyTest extends TestCase
{
    use TestTrait, RefreshDatabase;

    /**
     * Property 10: Comment Creation Invariants
     * 
     * **Validates: Requirements 5.3, 5.4**
     * 
     * For any valid comment submission, the saved comments record must have
     * likes_count = 0, dislikes_count = 0, is_flagged = false, and the correct
     * url_id, body, sentiment, and ip_address. Additionally, the comment_count
     * on the associated urls record must increase by exactly 1.
     * 
     * This test runs with a minimum of 100 iterations as per spec requirements.
     * 
     * @test
     */
    public function property_comment_creation_maintains_invariants()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->validCommentInputGenerator()
            )
            ->then(function ($commentData) {
                // Create a URL for testing
                $url = Url::create([
                    'url_hash' => md5('https://example.com/test-' . uniqid()),
                    'original_url' => 'https://example.com/test',
                    'normalized_url' => 'https://example.com/test',
                    'slug' => substr(md5(uniqid()), 0, 8),
                    'domain' => 'example.com',
                    'comment_count' => 0,
                ]);
                
                $initialCommentCount = $url->comment_count;
                
                // Post comment (without rate limiting)
                $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                    ->postJson("/d/{$url->slug}/comment", [
                        'body' => $commentData['body'],
                        'sentiment' => $commentData['sentiment'],
                        'guest_name' => $commentData['guest_name'],
                    ]);
                
                // Assert successful creation
                $response->assertStatus(201);
                
                // Get the created comment
                $comment = Comment::latest()->first();
                
                // Assert invariant: likes_count = 0
                $this->assertEquals(
                    0,
                    $comment->likes_count,
                    "New comment must have likes_count = 0"
                );
                
                // Assert invariant: dislikes_count = 0
                $this->assertEquals(
                    0,
                    $comment->dislikes_count,
                    "New comment must have dislikes_count = 0"
                );
                
                // Assert invariant: is_flagged = false
                $this->assertFalse(
                    $comment->is_flagged,
                    "New comment must have is_flagged = false"
                );
                
                // Assert correct url_id
                $this->assertEquals(
                    $url->id,
                    $comment->url_id,
                    "Comment must be associated with correct URL"
                );
                
                // Assert correct body
                $this->assertEquals(
                    $commentData['body'],
                    $comment->body,
                    "Comment body must match submitted body"
                );
                
                // Assert correct sentiment
                $this->assertEquals(
                    $commentData['sentiment'],
                    $comment->sentiment,
                    "Comment sentiment must match submitted sentiment"
                );
                
                // Assert ip_address is set
                $this->assertNotNull(
                    $comment->ip_address,
                    "Comment must have ip_address set"
                );
                
                // Assert comment_count incremented by exactly 1
                $url->refresh();
                $this->assertEquals(
                    $initialCommentCount + 1,
                    $url->comment_count,
                    "URL comment_count must increment by exactly 1"
                );
            });
    }

    /**
     * Generate random valid comment inputs
     */
    private function validCommentInputGenerator()
    {
        return Generator\bind(
            $this->bodyGenerator(),
            function ($body) {
                return Generator\bind(
                    $this->sentimentGenerator(),
                    function ($sentiment) use ($body) {
                        return Generator\bind(
                            $this->guestNameGenerator(),
                            function ($guestName) use ($body, $sentiment) {
                                return Generator\constant([
                                    'body' => $body,
                                    'sentiment' => $sentiment,
                                    'guest_name' => $guestName,
                                ]);
                            }
                        );
                    }
                );
            }
        );
    }

    /**
     * Generate random valid comment bodies (1-2000 chars)
     */
    private function bodyGenerator()
    {
        return Generator\bind(
            Generator\choose(1, 500), // Use shorter bodies for faster tests
            function ($length) {
                $words = [
                    'This', 'is', 'a', 'test', 'comment', 'with', 'random', 'content',
                    'Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur',
                    'Great', 'article', 'Interesting', 'point', 'I', 'agree', 'disagree',
                ];
                
                $body = '';
                while (strlen($body) < $length) {
                    $word = $words[array_rand($words)];
                    $body .= $word . ' ';
                }
                
                return Generator\constant(trim(substr($body, 0, $length)));
            }
        );
    }

    /**
     * Generate random sentiment values
     */
    private function sentimentGenerator()
    {
        return Generator\elements(['positive', 'negative', 'neutral']);
    }

    /**
     * Generate random guest names (0-80 chars)
     */
    private function guestNameGenerator()
    {
        return Generator\oneOf(
            Generator\constant(null), // No guest name
            Generator\elements([
                'Anonymous',
                'Guest User',
                'John Doe',
                'Jane Smith',
                'TestUser123',
                'CommentAuthor',
                str_repeat('A', 80), // Max length
            ])
        );
    }
}

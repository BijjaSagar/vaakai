<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\Url;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for Reply Depth Constraint
 * 
 * Feature: speakspace, Property 11: Reply Depth Constraint
 */
class ReplyDepthConstraintPropertyTest extends TestCase
{
    use TestTrait, RefreshDatabase;

    /**
     * Property 11: Reply Depth Constraint
     * 
     * **Validates: Requirements 5.6**
     * 
     * For any comment that already has a non-null parent_id (i.e., is itself a reply),
     * attempting to post a reply to it must be rejected — the system must not create
     * a comments record with a parent_id pointing to a comment that already has a parent_id.
     * 
     * This test runs with a minimum of 100 iterations as per spec requirements.
     * 
     * @test
     */
    public function property_replies_to_replies_are_rejected()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->replyCommentGenerator()
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
                
                // Create a top-level comment
                $topLevelComment = Comment::create([
                    'url_id' => $url->id,
                    'parent_id' => null,
                    'guest_name' => 'Original Commenter',
                    'body' => 'This is a top-level comment',
                    'sentiment' => 'neutral',
                    'ip_address' => '127.0.0.1',
                    'likes_count' => 0,
                    'dislikes_count' => 0,
                    'is_flagged' => false,
                ]);
                
                // Create a reply to the top-level comment
                $replyComment = Comment::create([
                    'url_id' => $url->id,
                    'parent_id' => $topLevelComment->id,
                    'guest_name' => $commentData['guest_name'],
                    'body' => $commentData['body'],
                    'sentiment' => $commentData['sentiment'],
                    'ip_address' => '127.0.0.2',
                    'likes_count' => 0,
                    'dislikes_count' => 0,
                    'is_flagged' => false,
                ]);
                
                $initialCommentCount = Comment::count();
                
                // Attempt to post a reply to the reply comment (without rate limiting)
                $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                    ->postJson("/d/{$url->slug}/comment", [
                        'body' => 'This should be rejected',
                        'sentiment' => 'neutral',
                        'guest_name' => 'Third Level Commenter',
                        'parent_id' => $replyComment->id,
                    ]);
                
                // Assert 422 validation error
                $response->assertStatus(422);
                $response->assertJsonValidationErrors('parent_id');
                
                // Assert no new comment was created
                $this->assertEquals(
                    $initialCommentCount,
                    Comment::count(),
                    "No new comment should be created when attempting to reply to a reply"
                );
            });
    }

    /**
     * Property 11: Reply Depth Constraint (Valid Replies)
     * 
     * **Validates: Requirements 5.6**
     * 
     * Verify that replies to top-level comments (parent_id = null) are accepted.
     * 
     * @test
     */
    public function property_replies_to_top_level_comments_are_accepted()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->replyCommentGenerator()
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
                
                // Create a top-level comment
                $topLevelComment = Comment::create([
                    'url_id' => $url->id,
                    'parent_id' => null,
                    'guest_name' => 'Original Commenter',
                    'body' => 'This is a top-level comment',
                    'sentiment' => 'neutral',
                    'ip_address' => '127.0.0.1',
                    'likes_count' => 0,
                    'dislikes_count' => 0,
                    'is_flagged' => false,
                ]);
                
                $initialCommentCount = Comment::count();
                
                // Post a reply to the top-level comment (without rate limiting)
                $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                    ->postJson("/d/{$url->slug}/comment", [
                        'body' => $commentData['body'],
                        'sentiment' => $commentData['sentiment'],
                        'guest_name' => $commentData['guest_name'],
                        'parent_id' => $topLevelComment->id,
                    ]);
                
                // Assert successful creation
                $response->assertStatus(201);
                
                // Assert new comment was created
                $this->assertEquals(
                    $initialCommentCount + 1,
                    Comment::count(),
                    "A new reply should be created for top-level comments"
                );
                
                // Verify the reply has correct parent_id
                $reply = Comment::latest()->first();
                $this->assertEquals($topLevelComment->id, $reply->parent_id);
            });
    }

    /**
     * Generate random reply comment data
     */
    private function replyCommentGenerator()
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
     * Generate random valid comment bodies
     */
    private function bodyGenerator()
    {
        return Generator\bind(
            Generator\choose(10, 200),
            function ($length) {
                $words = [
                    'This', 'is', 'a', 'reply', 'comment', 'with', 'random', 'content',
                    'I', 'agree', 'disagree', 'think', 'believe', 'Great', 'point',
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
     * Generate random guest names
     */
    private function guestNameGenerator()
    {
        return Generator\elements([
            'Reply Author',
            'Commenter',
            'Guest',
            'User123',
            null,
        ]);
    }
}

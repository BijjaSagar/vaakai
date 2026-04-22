<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature Tests for CommentController
 * 
 * Tests validation errors, successful save, comment_count increment,
 * rate limit, and reply depth enforcement.
 */
class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation error for missing body
     * 
     * Requirements: 5.1, 5.2
     */
    public function test_validation_error_for_missing_body(): void
    {
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'sentiment' => 'neutral',
            'guest_name' => 'Test User',
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('body');
    }

    /**
     * Test validation error for body exceeding max length
     * 
     * Requirements: 5.2
     */
    public function test_validation_error_for_body_exceeding_max_length(): void
    {
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => str_repeat('a', 2001),
            'sentiment' => 'neutral',
            'guest_name' => 'Test User',
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('body');
    }

    /**
     * Test validation error for missing sentiment
     * 
     * Requirements: 5.1
     */
    public function test_validation_error_for_missing_sentiment(): void
    {
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a test comment',
            'guest_name' => 'Test User',
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sentiment');
    }

    /**
     * Test validation error for invalid sentiment value
     * 
     * Requirements: 5.1
     */
    public function test_validation_error_for_invalid_sentiment(): void
    {
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a test comment',
            'sentiment' => 'invalid',
            'guest_name' => 'Test User',
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sentiment');
    }

    /**
     * Test validation error for guest_name exceeding max length
     * 
     * Requirements: 5.1
     */
    public function test_validation_error_for_guest_name_exceeding_max_length(): void
    {
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a test comment',
            'sentiment' => 'neutral',
            'guest_name' => str_repeat('a', 81),
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('guest_name');
    }

    /**
     * Test successful comment save
     * 
     * Requirements: 5.1, 5.3
     */
    public function test_successful_comment_save(): void
    {
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a test comment',
            'sentiment' => 'positive',
            'guest_name' => 'Test User',
        ]);
        
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'comment' => [
                'id',
                'url_id',
                'body',
                'sentiment',
                'guest_name',
                'ip_address',
                'likes_count',
                'dislikes_count',
                'is_flagged',
            ],
        ]);
        
        $this->assertDatabaseHas('comments', [
            'url_id' => $url->id,
            'body' => 'This is a test comment',
            'sentiment' => 'positive',
            'guest_name' => 'Test User',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);
    }

    /**
     * Test comment_count increment on URL
     * 
     * Requirements: 5.4
     */
    public function test_comment_count_increment(): void
    {
        $url = $this->createTestUrl();
        $initialCount = $url->comment_count;
        
        $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'First comment',
            'sentiment' => 'neutral',
            'guest_name' => 'User 1',
        ]);
        
        $url->refresh();
        $this->assertEquals($initialCount + 1, $url->comment_count);
        
        $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'Second comment',
            'sentiment' => 'positive',
            'guest_name' => 'User 2',
        ]);
        
        $url->refresh();
        $this->assertEquals($initialCount + 2, $url->comment_count);
    }

    /**
     * Test rate limit (429 after 10 comments per minute)
     * 
     * Requirements: 5.5
     */
    public function test_rate_limit_after_10_comments_per_minute(): void
    {
        \Illuminate\Support\Facades\Event::fake();
        
        $url = $this->createTestUrl();
        
        // Post 10 comments (should succeed)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson("/d/{$url->slug}/comment", [
                'body' => "Comment {$i}",
                'sentiment' => 'neutral',
                'guest_name' => "User {$i}",
            ]);
            
            $response->assertStatus(201);
        }
        
        // 11th comment should be rate limited
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This should be rate limited',
            'sentiment' => 'neutral',
            'guest_name' => 'Rate Limited User',
        ]);
        
        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    /**
     * Test successful reply to top-level comment
     * 
     * Requirements: 5.6
     */
    public function test_successful_reply_to_top_level_comment(): void
    {
        $url = $this->createTestUrl();
        
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
        
        // Post a reply
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a reply',
            'sentiment' => 'positive',
            'guest_name' => 'Reply Author',
            'parent_id' => $topLevelComment->id,
        ]);
        
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('comments', [
            'parent_id' => $topLevelComment->id,
            'body' => 'This is a reply',
        ]);
    }

    /**
     * Test reply depth enforcement (cannot reply to a reply)
     * 
     * Requirements: 5.6
     */
    public function test_reply_depth_enforcement(): void
    {
        $url = $this->createTestUrl();
        
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
        
        // Create a reply
        $replyComment = Comment::create([
            'url_id' => $url->id,
            'parent_id' => $topLevelComment->id,
            'guest_name' => 'Reply Author',
            'body' => 'This is a reply',
            'sentiment' => 'positive',
            'ip_address' => '127.0.0.2',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);
        
        // Attempt to reply to the reply
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This should be rejected',
            'sentiment' => 'neutral',
            'guest_name' => 'Third Level Commenter',
            'parent_id' => $replyComment->id,
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('parent_id');
    }

    /**
     * Test validation error for non-existent parent_id
     * 
     * Requirements: 5.6
     */
    public function test_validation_error_for_non_existent_parent_id(): void
    {
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a reply',
            'sentiment' => 'neutral',
            'guest_name' => 'Reply Author',
            'parent_id' => 99999, // Non-existent ID
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('parent_id');
    }

    /**
     * Test comment without guest_name (optional field)
     * 
     * Requirements: 5.1
     */
    public function test_comment_without_guest_name(): void
    {
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'Anonymous comment',
            'sentiment' => 'neutral',
        ]);
        
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('comments', [
            'body' => 'Anonymous comment',
            'guest_name' => null,
        ]);
    }

    /**
     * Helper method to create a test URL
     */
    private function createTestUrl(): Url
    {
        return Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => substr(md5(uniqid()), 0, 8),
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);
    }
}

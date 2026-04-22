<?php

namespace Tests\Feature;

use App\Events\CommentPosted;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Feature Tests for Real-Time Comment Broadcasting
 * 
 * Tests that CommentPosted event is fired with correct payload
 * when a comment is saved.
 * 
 * Requirements: 8.1, 8.2
 */
class CommentBroadcastTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test CommentPosted event is fired on comment save
     * 
     * Requirements: 8.1, 8.2
     */
    public function test_comment_posted_event_is_fired_on_comment_save(): void
    {
        Event::fake([CommentPosted::class]);
        
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a test comment',
            'sentiment' => 'positive',
            'guest_name' => 'Test User',
        ]);
        
        $response->assertStatus(201);
        
        Event::assertDispatched(CommentPosted::class);
    }

    /**
     * Test CommentPosted event contains correct payload
     * 
     * Requirements: 8.1, 8.2
     */
    public function test_comment_posted_event_contains_correct_payload(): void
    {
        Event::fake([CommentPosted::class]);
        
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a test comment',
            'sentiment' => 'positive',
            'guest_name' => 'Test User',
        ]);
        
        $response->assertStatus(201);
        
        Event::assertDispatched(function (CommentPosted $event) use ($url) {
            // Verify the event has the correct slug
            $this->assertEquals($url->slug, $event->slug);
            
            // Verify the comment data
            $this->assertEquals('This is a test comment', $event->comment->body);
            $this->assertEquals('positive', $event->comment->sentiment);
            $this->assertEquals('Test User', $event->comment->guest_name);
            $this->assertEquals(0, $event->comment->likes_count);
            $this->assertEquals(0, $event->comment->dislikes_count);
            $this->assertNull($event->comment->parent_id);
            
            return true;
        });
    }

    /**
     * Test CommentPosted event broadcasts on correct channel
     * 
     * Requirements: 8.1, 8.2
     */
    public function test_comment_posted_event_broadcasts_on_correct_channel(): void
    {
        Event::fake([CommentPosted::class]);
        
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a test comment',
            'sentiment' => 'neutral',
            'guest_name' => 'Test User',
        ]);
        
        $response->assertStatus(201);
        $comment = $response->json('comment');
        
        // Create the event and verify channel
        $event = new CommentPosted(
            \App\Models\Comment::find($comment['id']),
            $url->slug
        );
        
        $channel = $event->broadcastOn();
        $this->assertEquals('discussion.' . $url->slug, $channel->name);
    }

    /**
     * Test CommentPosted event broadcast payload structure
     * 
     * Requirements: 8.1, 8.2
     */
    public function test_comment_posted_event_broadcast_payload_structure(): void
    {
        Event::fake([CommentPosted::class]);
        
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a test comment',
            'sentiment' => 'negative',
            'guest_name' => 'Test User',
        ]);
        
        $response->assertStatus(201);
        $comment = $response->json('comment');
        
        // Create the event and verify broadcast payload
        $event = new CommentPosted(
            \App\Models\Comment::find($comment['id']),
            $url->slug
        );
        
        $payload = $event->broadcastWith();
        
        // Verify all required fields are present
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('guest_name', $payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertArrayHasKey('sentiment', $payload);
        $this->assertArrayHasKey('likes_count', $payload);
        $this->assertArrayHasKey('dislikes_count', $payload);
        $this->assertArrayHasKey('created_at', $payload);
        $this->assertArrayHasKey('parent_id', $payload);
        
        // Verify payload values
        $this->assertEquals($comment['id'], $payload['id']);
        $this->assertEquals('Test User', $payload['guest_name']);
        $this->assertEquals('This is a test comment', $payload['body']);
        $this->assertEquals('negative', $payload['sentiment']);
        $this->assertEquals(0, $payload['likes_count']);
        $this->assertEquals(0, $payload['dislikes_count']);
        $this->assertNull($payload['parent_id']);
    }

    /**
     * Test CommentPosted event includes parent_id for replies
     * 
     * Requirements: 8.1, 8.2
     */
    public function test_comment_posted_event_includes_parent_id_for_replies(): void
    {
        Event::fake([CommentPosted::class]);
        
        $url = $this->createTestUrl();
        
        // Create a top-level comment
        $topLevelResponse = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'Top-level comment',
            'sentiment' => 'neutral',
            'guest_name' => 'Original User',
        ]);
        
        $topLevelComment = $topLevelResponse->json('comment');
        
        // Create a reply
        $replyResponse = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'This is a reply',
            'sentiment' => 'positive',
            'guest_name' => 'Reply User',
            'parent_id' => $topLevelComment['id'],
        ]);
        
        $replyResponse->assertStatus(201);
        
        // Verify the event for the reply includes parent_id
        Event::assertDispatched(function (CommentPosted $event) use ($topLevelComment) {
            if ($event->comment->body === 'This is a reply') {
                $this->assertEquals($topLevelComment['id'], $event->comment->parent_id);
                return true;
            }
            return false;
        });
    }

    /**
     * Test CommentPosted event broadcast name
     * 
     * Requirements: 8.1, 8.2
     */
    public function test_comment_posted_event_broadcast_name(): void
    {
        Event::fake([CommentPosted::class]);
        
        $url = $this->createTestUrl();
        
        $response = $this->postJson("/d/{$url->slug}/comment", [
            'body' => 'Test comment',
            'sentiment' => 'neutral',
            'guest_name' => 'Test User',
        ]);
        
        $response->assertStatus(201);
        $comment = $response->json('comment');
        
        // Create the event and verify broadcast name
        $event = new CommentPosted(
            \App\Models\Comment::find($comment['id']),
            $url->slug
        );
        
        $this->assertEquals('CommentPosted', $event->broadcastAs());
    }

    /**
     * Helper method to create a test URL
     */
    private function createTestUrl(): Url
    {
        return Url::create([
            'url_hash' => md5('https://example.com/test-' . uniqid()),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => substr(md5(uniqid()), 0, 8),
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);
    }
}

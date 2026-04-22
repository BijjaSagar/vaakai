<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for comment sorting
 * 
 * Requirements: 4.3, 4.4
 */
class CommentSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_comments_can_be_sorted_by_newest(): void
    {
        $url = $this->createTestUrl();
        
        // Create comments with specific timestamps
        $comment1 = Comment::create([
            'url_id' => $url->id,
            'body' => 'First comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(3),
        ]);
        
        $comment2 = Comment::create([
            'url_id' => $url->id,
            'body' => 'Second comment',
            'sentiment' => 'positive',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(1),
        ]);
        
        $comment3 = Comment::create([
            'url_id' => $url->id,
            'body' => 'Third comment',
            'sentiment' => 'negative',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(2),
        ]);
        
        $response = $this->getJson("/d/{$url->slug}/comments?sort=newest");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should be ordered: comment2, comment3, comment1 (by created_at DESC)
        $this->assertCount(3, $data);
        $this->assertEquals('Second comment', $data[0]['body']);
        $this->assertEquals('Third comment', $data[1]['body']);
        $this->assertEquals('First comment', $data[2]['body']);
    }

    public function test_comments_can_be_sorted_by_top_score(): void
    {
        $url = $this->createTestUrl();
        
        // Create comments with different vote counts
        $comment1 = Comment::create([
            'url_id' => $url->id,
            'body' => 'Low score comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 5,
            'dislikes_count' => 10, // Score: -5
            'is_flagged' => false,
        ]);
        
        $comment2 = Comment::create([
            'url_id' => $url->id,
            'body' => 'High score comment',
            'sentiment' => 'positive',
            'ip_address' => '127.0.0.1',
            'likes_count' => 20,
            'dislikes_count' => 5, // Score: 15
            'is_flagged' => false,
        ]);
        
        $comment3 = Comment::create([
            'url_id' => $url->id,
            'body' => 'Medium score comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 10,
            'dislikes_count' => 5, // Score: 5
            'is_flagged' => false,
        ]);
        
        $response = $this->getJson("/d/{$url->slug}/comments?sort=top");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should be ordered: comment2 (15), comment3 (5), comment1 (-5)
        $this->assertEquals($comment2->id, $data[0]['id']);
        $this->assertEquals($comment3->id, $data[1]['id']);
        $this->assertEquals($comment1->id, $data[2]['id']);
    }

    public function test_comments_can_be_filtered_by_positive_sentiment(): void
    {
        $url = $this->createTestUrl();
        
        // Create comments with different sentiments
        $positiveComment1 = Comment::create([
            'url_id' => $url->id,
            'body' => 'Positive comment 1',
            'sentiment' => 'positive',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(1),
        ]);
        
        $negativeComment = Comment::create([
            'url_id' => $url->id,
            'body' => 'Negative comment',
            'sentiment' => 'negative',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(2),
        ]);
        
        $positiveComment2 = Comment::create([
            'url_id' => $url->id,
            'body' => 'Positive comment 2',
            'sentiment' => 'positive',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(3),
        ]);
        
        $neutralComment = Comment::create([
            'url_id' => $url->id,
            'body' => 'Neutral comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(4),
        ]);
        
        $response = $this->getJson("/d/{$url->slug}/comments?sort=positive");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only return positive comments, ordered by newest
        $this->assertCount(2, $data);
        $this->assertEquals('Positive comment 1', $data[0]['body']);
        $this->assertEquals('positive', $data[0]['sentiment']);
        $this->assertEquals('Positive comment 2', $data[1]['body']);
        $this->assertEquals('positive', $data[1]['sentiment']);
    }

    public function test_comments_can_be_filtered_by_negative_sentiment(): void
    {
        $url = $this->createTestUrl();
        
        // Create comments with different sentiments
        $negativeComment1 = Comment::create([
            'url_id' => $url->id,
            'body' => 'Negative comment 1',
            'sentiment' => 'negative',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(1),
        ]);
        
        $positiveComment = Comment::create([
            'url_id' => $url->id,
            'body' => 'Positive comment',
            'sentiment' => 'positive',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(2),
        ]);
        
        $negativeComment2 = Comment::create([
            'url_id' => $url->id,
            'body' => 'Negative comment 2',
            'sentiment' => 'negative',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(3),
        ]);
        
        $neutralComment = Comment::create([
            'url_id' => $url->id,
            'body' => 'Neutral comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(4),
        ]);
        
        $response = $this->getJson("/d/{$url->slug}/comments?sort=negative");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only return negative comments, ordered by newest
        $this->assertCount(2, $data);
        $this->assertEquals('Negative comment 1', $data[0]['body']);
        $this->assertEquals('negative', $data[0]['sentiment']);
        $this->assertEquals('Negative comment 2', $data[1]['body']);
        $this->assertEquals('negative', $data[1]['sentiment']);
    }

    public function test_comment_sorting_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/d/unknown-slug/comments?sort=newest');
        
        $response->assertStatus(404);
    }

    public function test_comment_sorting_defaults_to_newest_when_no_sort_parameter_provided(): void
    {
        $url = $this->createTestUrl();
        
        $comment1 = Comment::create([
            'url_id' => $url->id,
            'body' => 'Old comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(2),
        ]);
        
        $comment2 = Comment::create([
            'url_id' => $url->id,
            'body' => 'New comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
            'created_at' => now()->subHours(1),
        ]);
        
        $response = $this->getJson("/d/{$url->slug}/comments");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should default to newest first
        $this->assertEquals($comment2->id, $data[0]['id']);
        $this->assertEquals($comment1->id, $data[1]['id']);
    }

    public function test_comment_sorting_only_returns_top_level_comments(): void
    {
        $url = $this->createTestUrl();
        
        $parentComment = Comment::create([
            'url_id' => $url->id,
            'body' => 'Parent comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);
        
        $replyComment = Comment::create([
            'url_id' => $url->id,
            'parent_id' => $parentComment->id,
            'body' => 'Reply comment',
            'sentiment' => 'positive',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);
        
        $response = $this->getJson("/d/{$url->slug}/comments?sort=newest");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only return the parent comment, not the reply
        $this->assertCount(1, $data);
        $this->assertEquals($parentComment->id, $data[0]['id']);
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


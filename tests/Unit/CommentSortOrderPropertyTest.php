<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\Url;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase as LaravelTestCase;

/**
 * Property-Based Tests for Comment Sort Order Correctness
 * 
 * Feature: speakspace, Property 8: Comment Sort Order Correctness
 */
class CommentSortOrderPropertyTest extends LaravelTestCase
{
    use TestTrait;
    use RefreshDatabase;

    /**
     * Property 8: Comment Sort Order Correctness - Newest
     * 
     * **Validates: Requirements 4.3, 4.4, 9.1**
     * 
     * For any set of comments on a discussion, the API endpoint GET /d/{slug}/comments
     * with sort=newest must return comments in descending created_at order.
     * 
     * @test
     */
    public function property_newest_sort_returns_comments_in_descending_created_at_order()
    {
        $this
            ->limitTo(5)
            ->forAll(
                Generator\choose(3, 10) // Number of comments
            )
            ->then(function ($commentCount) {
                // Create a URL
                $url = $this->createTestUrl();
                
                // Generate random comments with varying timestamps
                $baseTime = now()->subDays(10);
                
                for ($i = 0; $i < $commentCount; $i++) {
                    Comment::create([
                        'url_id' => $url->id,
                        'parent_id' => null,
                        'guest_name' => 'User ' . $i,
                        'body' => 'Comment body ' . $i,
                        'sentiment' => ['positive', 'negative', 'neutral'][array_rand(['positive', 'negative', 'neutral'])],
                        'likes_count' => rand(0, 50),
                        'dislikes_count' => rand(0, 50),
                        'ip_address' => '127.0.0.1',
                        'is_flagged' => false,
                        'created_at' => $baseTime->copy()->addMinutes(rand(0, 1000)),
                    ]);
                }
                
                // Fetch with 'newest' sort
                $response = $this->getJson("/d/{$url->slug}/comments?sort=newest");
                
                $response->assertStatus(200);
                $data = $response->json('data');
                
                // Assert comments are ordered by created_at DESC
                for ($i = 0; $i < count($data) - 1; $i++) {
                    $current = strtotime($data[$i]['created_at']);
                    $next = strtotime($data[$i + 1]['created_at']);
                    $this->assertGreaterThanOrEqual(
                        $next,
                        $current,
                        "Comments must be ordered by created_at DESC. " .
                        "Comment at index {$i} has timestamp {$current}, " .
                        "but comment at index " . ($i + 1) . " has timestamp {$next}"
                    );
                }
            });
    }

    /**
     * Property 8: Comment Sort Order Correctness - Top
     * 
     * **Validates: Requirements 4.3, 4.4, 9.1**
     * 
     * For any set of comments on a discussion, the API endpoint GET /d/{slug}/comments
     * with sort=top must return comments ordered by (likes_count - dislikes_count) DESC.
     * 
     * @test
     */
    public function property_top_sort_returns_comments_in_descending_score_order()
    {
        $this
            ->limitTo(5)
            ->forAll(
                Generator\choose(3, 10) // Number of comments
            )
            ->then(function ($commentCount) {
                // Create a URL
                $url = $this->createTestUrl();
                
                // Generate random comments with varying vote counts
                for ($i = 0; $i < $commentCount; $i++) {
                    $likesCount = rand(0, 50);
                    $dislikesCount = rand(0, 50);
                    
                    Comment::create([
                        'url_id' => $url->id,
                        'parent_id' => null,
                        'guest_name' => 'User ' . $i,
                        'body' => 'Comment body ' . $i,
                        'sentiment' => ['positive', 'negative', 'neutral'][array_rand(['positive', 'negative', 'neutral'])],
                        'likes_count' => $likesCount,
                        'dislikes_count' => $dislikesCount,
                        'ip_address' => '127.0.0.1',
                        'is_flagged' => false,
                    ]);
                }
                
                // Fetch with 'top' sort
                $response = $this->getJson("/d/{$url->slug}/comments?sort=top");
                
                $response->assertStatus(200);
                $data = $response->json('data');
                
                // Assert comments are ordered by (likes_count - dislikes_count) DESC
                for ($i = 0; $i < count($data) - 1; $i++) {
                    $currentScore = $data[$i]['likes_count'] - $data[$i]['dislikes_count'];
                    $nextScore = $data[$i + 1]['likes_count'] - $data[$i + 1]['dislikes_count'];
                    $this->assertGreaterThanOrEqual(
                        $nextScore,
                        $currentScore,
                        "Comments must be ordered by score DESC. " .
                        "Comment at index {$i} has score {$currentScore}, " .
                        "but comment at index " . ($i + 1) . " has score {$nextScore}"
                    );
                }
            });
    }

    /**
     * Property 8: Comment Sort Order Correctness - Positive
     * 
     * **Validates: Requirements 4.3, 4.4, 9.1**
     * 
     * For any set of comments on a discussion, the API endpoint GET /d/{slug}/comments
     * with sort=positive must return only comments with sentiment='positive', ordered by created_at DESC.
     * 
     * @test
     */
    public function property_positive_sort_returns_only_positive_comments()
    {
        $this
            ->limitTo(5)
            ->forAll(
                Generator\choose(3, 10) // Number of comments
            )
            ->then(function ($commentCount) {
                // Create a URL
                $url = $this->createTestUrl();
                
                // Generate random comments with varying sentiments
                $positiveCount = 0;
                
                for ($i = 0; $i < $commentCount; $i++) {
                    $sentiment = ['positive', 'negative', 'neutral'][rand(0, 2)];
                    if ($sentiment === 'positive') {
                        $positiveCount++;
                    }
                    
                    Comment::create([
                        'url_id' => $url->id,
                        'parent_id' => null,
                        'guest_name' => 'User ' . $i,
                        'body' => 'Comment body ' . $i,
                        'sentiment' => $sentiment,
                        'likes_count' => rand(0, 50),
                        'dislikes_count' => rand(0, 50),
                        'ip_address' => '127.0.0.1',
                        'is_flagged' => false,
                        'created_at' => now()->subMinutes(rand(0, 1000)),
                    ]);
                }
                
                // Fetch with 'positive' sort
                $response = $this->getJson("/d/{$url->slug}/comments?sort=positive");
                
                $response->assertStatus(200);
                $data = $response->json('data');
                
                // Assert all returned comments have sentiment = 'positive'
                foreach ($data as $comment) {
                    $this->assertEquals(
                        'positive',
                        $comment['sentiment'],
                        "All comments in positive sort must have sentiment='positive'"
                    );
                }
                
                // Assert count matches expected positive comments
                $this->assertCount(
                    $positiveCount,
                    $data,
                    "Expected {$positiveCount} positive comments, got " . count($data)
                );
                
                // Assert comments are ordered by created_at DESC
                for ($i = 0; $i < count($data) - 1; $i++) {
                    $current = strtotime($data[$i]['created_at']);
                    $next = strtotime($data[$i + 1]['created_at']);
                    $this->assertGreaterThanOrEqual(
                        $next,
                        $current,
                        "Positive comments must be ordered by created_at DESC"
                    );
                }
            });
    }

    /**
     * Property 8: Comment Sort Order Correctness - Negative
     * 
     * **Validates: Requirements 4.3, 4.4, 9.1**
     * 
     * For any set of comments on a discussion, the API endpoint GET /d/{slug}/comments
     * with sort=negative must return only comments with sentiment='negative', ordered by created_at DESC.
     * 
     * @test
     */
    public function property_negative_sort_returns_only_negative_comments()
    {
        $this
            ->limitTo(5)
            ->forAll(
                Generator\choose(3, 10) // Number of comments
            )
            ->then(function ($commentCount) {
                // Create a URL
                $url = $this->createTestUrl();
                
                // Generate random comments with varying sentiments
                $negativeCount = 0;
                
                for ($i = 0; $i < $commentCount; $i++) {
                    $sentiment = ['positive', 'negative', 'neutral'][rand(0, 2)];
                    if ($sentiment === 'negative') {
                        $negativeCount++;
                    }
                    
                    Comment::create([
                        'url_id' => $url->id,
                        'parent_id' => null,
                        'guest_name' => 'User ' . $i,
                        'body' => 'Comment body ' . $i,
                        'sentiment' => $sentiment,
                        'likes_count' => rand(0, 50),
                        'dislikes_count' => rand(0, 50),
                        'ip_address' => '127.0.0.1',
                        'is_flagged' => false,
                        'created_at' => now()->subMinutes(rand(0, 1000)),
                    ]);
                }
                
                // Fetch with 'negative' sort
                $response = $this->getJson("/d/{$url->slug}/comments?sort=negative");
                
                $response->assertStatus(200);
                $data = $response->json('data');
                
                // Assert all returned comments have sentiment = 'negative'
                foreach ($data as $comment) {
                    $this->assertEquals(
                        'negative',
                        $comment['sentiment'],
                        "All comments in negative sort must have sentiment='negative'"
                    );
                }
                
                // Assert count matches expected negative comments
                $this->assertCount(
                    $negativeCount,
                    $data,
                    "Expected {$negativeCount} negative comments, got " . count($data)
                );
                
                // Assert comments are ordered by created_at DESC
                for ($i = 0; $i < count($data) - 1; $i++) {
                    $current = strtotime($data[$i]['created_at']);
                    $next = strtotime($data[$i + 1]['created_at']);
                    $this->assertGreaterThanOrEqual(
                        $next,
                        $current,
                        "Negative comments must be ordered by created_at DESC"
                    );
                }
            });
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

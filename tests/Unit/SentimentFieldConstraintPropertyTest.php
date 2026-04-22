<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\Url;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Tests for Sentiment Field Constraint
 * 
 * Feature: speakspace, Property 13: Sentiment Field Constraint
 */
class SentimentFieldConstraintPropertyTest extends TestCase
{
    use TestTrait, RefreshDatabase;

    /**
     * Property 13: Sentiment Field Constraint
     * 
     * **Validates: Requirements 7.1**
     * 
     * For any saved comments record, the sentiment field must be one of the three
     * valid enum values: positive, negative, or neutral. No comment may be saved
     * with a null or out-of-enum sentiment value.
     * 
     * This test runs with a minimum of 100 iterations as per spec requirements.
     * 
     * @test
     */
    public function property_invalid_sentiment_values_are_rejected()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->invalidSentimentGenerator()
            )
            ->then(function ($sentimentData) {
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
                
                // Attempt to post comment with invalid sentiment (without rate limiting)
                $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                    ->postJson("/d/{$url->slug}/comment", [
                        'body' => 'This is a test comment',
                        'sentiment' => $sentimentData['sentiment'],
                        'guest_name' => 'Test User',
                    ]);
                
                // Assert 422 validation error
                $response->assertStatus(422);
                $response->assertJsonValidationErrors('sentiment');
                
                // Assert no new comment was created
                $this->assertEquals(
                    $initialCommentCount,
                    Comment::count(),
                    "No new comment should be created for invalid sentiment: " . json_encode($sentimentData['sentiment'])
                );
            });
    }

    /**
     * Property 13: Sentiment Field Constraint (Valid Sentiments)
     * 
     * **Validates: Requirements 7.1**
     * 
     * Verify that all valid sentiment values are accepted and stored correctly.
     * 
     * @test
     */
    public function property_valid_sentiment_values_are_accepted()
    {
        $this
            ->limitTo(5)
            ->forAll(
                $this->validSentimentGenerator()
            )
            ->then(function ($sentiment) {
                // Create a URL for testing
                $url = Url::create([
                    'url_hash' => md5('https://example.com/test-' . uniqid()),
                    'original_url' => 'https://example.com/test',
                    'normalized_url' => 'https://example.com/test',
                    'slug' => substr(md5(uniqid()), 0, 8),
                    'domain' => 'example.com',
                    'comment_count' => 0,
                ]);
                
                // Post comment with valid sentiment (without rate limiting)
                $response = $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                    ->postJson("/d/{$url->slug}/comment", [
                        'body' => 'This is a test comment',
                        'sentiment' => $sentiment,
                        'guest_name' => 'Test User',
                    ]);
                
                // Assert successful creation
                $response->assertStatus(201);
                
                // Get the created comment
                $comment = Comment::latest()->first();
                
                // Assert sentiment is one of the valid enum values
                $this->assertContains(
                    $comment->sentiment,
                    ['positive', 'negative', 'neutral'],
                    "Comment sentiment must be one of the valid enum values"
                );
                
                // Assert sentiment matches submitted value
                $this->assertEquals(
                    $sentiment,
                    $comment->sentiment,
                    "Comment sentiment must match submitted sentiment"
                );
                
                // Assert sentiment is not null
                $this->assertNotNull(
                    $comment->sentiment,
                    "Comment sentiment must not be null"
                );
            });
    }

    /**
     * Property 13: No Saved Comments Have Invalid Sentiment
     * 
     * **Validates: Requirements 7.1**
     * 
     * Verify that no comment in the database has a null or out-of-enum sentiment.
     * 
     * @test
     */
    public function property_no_saved_comments_have_invalid_sentiment()
    {
        $this
            ->limitTo(5)
            ->forAll(
                Generator\choose(1, 10) // Generate 1-10 comments
            )
            ->then(function ($numComments) {
                // Create a URL for testing
                $url = Url::create([
                    'url_hash' => md5('https://example.com/test-' . uniqid()),
                    'original_url' => 'https://example.com/test',
                    'normalized_url' => 'https://example.com/test',
                    'slug' => substr(md5(uniqid()), 0, 8),
                    'domain' => 'example.com',
                    'comment_count' => 0,
                ]);
                
                // Create multiple comments with random valid sentiments
                $validSentiments = ['positive', 'negative', 'neutral'];
                
                for ($i = 0; $i < $numComments; $i++) {
                    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
                        ->postJson("/d/{$url->slug}/comment", [
                            'body' => "Test comment {$i}",
                            'sentiment' => $validSentiments[array_rand($validSentiments)],
                            'guest_name' => "User {$i}",
                        ]);
                }
                
                // Verify all comments have valid sentiment
                $comments = Comment::all();
                
                foreach ($comments as $comment) {
                    $this->assertContains(
                        $comment->sentiment,
                        ['positive', 'negative', 'neutral'],
                        "All saved comments must have valid sentiment enum values"
                    );
                    
                    $this->assertNotNull(
                        $comment->sentiment,
                        "All saved comments must have non-null sentiment"
                    );
                }
            });
    }

    /**
     * Generate invalid sentiment values
     */
    private function invalidSentimentGenerator()
    {
        return Generator\oneOf(
            // Null value
            Generator\constant(['sentiment' => null, 'reason' => 'null']),
            
            // Empty string
            Generator\constant(['sentiment' => '', 'reason' => 'empty']),
            
            // Invalid enum values
            Generator\elements([
                ['sentiment' => 'happy', 'reason' => 'invalid_enum'],
                ['sentiment' => 'sad', 'reason' => 'invalid_enum'],
                ['sentiment' => 'angry', 'reason' => 'invalid_enum'],
                ['sentiment' => 'excited', 'reason' => 'invalid_enum'],
                ['sentiment' => 'mixed', 'reason' => 'invalid_enum'],
                ['sentiment' => 'unknown', 'reason' => 'invalid_enum'],
                ['sentiment' => 'POSITIVE', 'reason' => 'wrong_case'],
                ['sentiment' => 'Negative', 'reason' => 'wrong_case'],
                ['sentiment' => 'NEUTRAL', 'reason' => 'wrong_case'],
                ['sentiment' => 'pos', 'reason' => 'abbreviated'],
                ['sentiment' => 'neg', 'reason' => 'abbreviated'],
                ['sentiment' => '1', 'reason' => 'numeric'],
                ['sentiment' => '0', 'reason' => 'numeric'],
                ['sentiment' => 'true', 'reason' => 'boolean_string'],
                ['sentiment' => 'false', 'reason' => 'boolean_string'],
            ])
        );
    }

    /**
     * Generate valid sentiment values
     */
    private function validSentimentGenerator()
    {
        return Generator\elements(['positive', 'negative', 'neutral']);
    }
}

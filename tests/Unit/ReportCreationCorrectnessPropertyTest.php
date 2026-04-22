<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\Url;
use Eris\Generator;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Property-Based Tests for Report Creation Correctness
 * 
 * Feature: speakspace, Property 14: Report Creation Correctness
 */
class ReportCreationCorrectnessPropertyTest extends TestCase
{
    use TestTrait, RefreshDatabase;

    /**
     * Property 14: Report Creation Correctness
     * 
     * **Validates: Requirements 10.1**
     * 
     * For any valid report submission, the saved reports record must contain
     * the correct comment_id, a valid reason enum value, and the submitter's
     * ip_address.
     * 
     * This test runs with a minimum of 100 iterations as per spec requirements.
     * 
     * @test
     */
    public function property_report_creation_has_correct_fields()
    {
        $this
            ->limitTo(100)
            ->forAll(
                $this->validReportInputGenerator()
            )
            ->then(function ($reportData) {
                // Create a URL and comment for testing
                $url = Url::create([
                    'url_hash' => md5('https://example.com/test-' . uniqid()),
                    'original_url' => 'https://example.com/test',
                    'normalized_url' => 'https://example.com/test',
                    'slug' => substr(md5(uniqid()), 0, 8),
                    'domain' => 'example.com',
                    'comment_count' => 0,
                ]);
                
                $comment = Comment::create([
                    'url_id' => $url->id,
                    'body' => 'Test comment body',
                    'sentiment' => 'neutral',
                    'ip_address' => '127.0.0.1',
                    'likes_count' => 0,
                    'dislikes_count' => 0,
                    'is_flagged' => false,
                ]);
                
                // Submit report
                $response = $this->postJson("/d/{$url->slug}/comments/{$comment->id}/report", [
                    'reason' => $reportData['reason'],
                ]);
                
                // Assert successful creation
                $response->assertStatus(200);
                $response->assertJson(['success' => true]);
                
                // Get the created report
                $report = DB::table('reports')->latest('id')->first();
                
                // Assert correct comment_id
                $this->assertEquals(
                    $comment->id,
                    $report->comment_id,
                    "Report must be associated with correct comment"
                );
                
                // Assert valid reason enum value
                $this->assertContains(
                    $report->reason,
                    ['spam', 'hate', 'fake', 'other'],
                    "Report reason must be a valid enum value"
                );
                
                // Assert correct reason matches submitted reason
                $this->assertEquals(
                    $reportData['reason'],
                    $report->reason,
                    "Report reason must match submitted reason"
                );
                
                // Assert ip_address is set
                $this->assertNotNull(
                    $report->ip_address,
                    "Report must have ip_address set"
                );
                
                // Assert ip_address is a valid format (basic check)
                $this->assertMatchesRegularExpression(
                    '/^[\d.:a-fA-F]+$/',
                    $report->ip_address,
                    "Report ip_address must be a valid IP format"
                );
            });
    }

    /**
     * Generate random valid report inputs
     */
    private function validReportInputGenerator()
    {
        return Generator\bind(
            $this->reasonGenerator(),
            function ($reason) {
                return Generator\constant([
                    'reason' => $reason,
                ]);
            }
        );
    }

    /**
     * Generate random reason values
     */
    private function reasonGenerator()
    {
        return Generator\elements(['spam', 'hate', 'fake', 'other']);
    }
}

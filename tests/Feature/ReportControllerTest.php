<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_successfully_creates_a_report_with_valid_reason()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        $comment = Comment::create([
            'url_id' => $url->id,
            'body' => 'This is a test comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);

        $response = $this->postJson("/d/{$url->slug}/comments/{$comment->id}/report", [
            'reason' => 'spam',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Report submitted successfully',
        ]);

        $this->assertDatabaseHas('reports', [
            'comment_id' => $comment->id,
            'reason' => 'spam',
        ]);
    }

    /**
     * @test
     */
    public function it_stores_correct_ip_address_in_report()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        $comment = Comment::create([
            'url_id' => $url->id,
            'body' => 'This is a test comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);

        $response = $this->postJson("/d/{$url->slug}/comments/{$comment->id}/report", [
            'reason' => 'hate',
        ]);

        $response->assertStatus(200);

        $report = DB::table('reports')->where('comment_id', $comment->id)->first();
        $this->assertNotNull($report->ip_address);
    }

    /**
     * @test
     */
    public function it_accepts_all_valid_reason_enum_values()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        $comment = Comment::create([
            'url_id' => $url->id,
            'body' => 'This is a test comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);

        $validReasons = ['spam', 'hate', 'fake', 'other'];

        foreach ($validReasons as $reason) {
            $response = $this->postJson("/d/{$url->slug}/comments/{$comment->id}/report", [
                'reason' => $reason,
            ]);

            $response->assertStatus(200);
            $this->assertDatabaseHas('reports', [
                'comment_id' => $comment->id,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * @test
     */
    public function it_rejects_invalid_reason_value()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        $comment = Comment::create([
            'url_id' => $url->id,
            'body' => 'This is a test comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);

        $response = $this->postJson("/d/{$url->slug}/comments/{$comment->id}/report", [
            'reason' => 'invalid_reason',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);

        $this->assertDatabaseMissing('reports', [
            'comment_id' => $comment->id,
        ]);
    }

    /**
     * @test
     */
    public function it_requires_reason_field()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        $comment = Comment::create([
            'url_id' => $url->id,
            'body' => 'This is a test comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);

        $response = $this->postJson("/d/{$url->slug}/comments/{$comment->id}/report", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);

        $this->assertDatabaseMissing('reports', [
            'comment_id' => $comment->id,
        ]);
    }

    /**
     * @test
     */
    public function it_returns_404_for_nonexistent_comment()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        $response = $this->postJson("/d/{$url->slug}/comments/99999/report", [
            'reason' => 'spam',
        ]);

        $response->assertStatus(404);
    }

    /**
     * @test
     */
    public function it_allows_multiple_reports_on_same_comment()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        $comment = Comment::create([
            'url_id' => $url->id,
            'body' => 'This is a test comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);

        // First report
        $response1 = $this->postJson("/d/{$url->slug}/comments/{$comment->id}/report", [
            'reason' => 'spam',
        ]);
        $response1->assertStatus(200);

        // Second report
        $response2 = $this->postJson("/d/{$url->slug}/comments/{$comment->id}/report", [
            'reason' => 'hate',
        ]);
        $response2->assertStatus(200);

        // Both reports should exist
        $reportCount = DB::table('reports')->where('comment_id', $comment->id)->count();
        $this->assertEquals(2, $reportCount);
    }
}

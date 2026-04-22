<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Report;
use App\Models\Url;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_requires_authentication_to_access_admin_dashboard()
    {
        $response = $this->get('/admin');

        $response->assertStatus(302);
    }

    /**
     * @test
     */
    public function it_displays_admin_dashboard_for_authenticated_users()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
    }

    /**
     * @test
     */
    public function it_displays_flagged_comments_on_dashboard()
    {
        $user = User::factory()->create();

        $url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'title' => 'Test Article',
            'comment_count' => 0,
        ]);

        $flaggedComment = Comment::create([
            'url_id' => $url->id,
            'body' => 'This is a flagged comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => true,
        ]);

        $normalComment = Comment::create([
            'url_id' => $url->id,
            'body' => 'This is a normal comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('This is a flagged comment');
        $response->assertDontSee('This is a normal comment');
    }

    /**
     * @test
     */
    public function it_displays_platform_statistics()
    {
        $user = User::factory()->create();

        // Create test data
        $url1 = Url::create([
            'url_hash' => md5('https://example.com/test1'),
            'original_url' => 'https://example.com/test1',
            'normalized_url' => 'https://example.com/test1',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        $url2 = Url::create([
            'url_hash' => md5('https://example.com/test2'),
            'original_url' => 'https://example.com/test2',
            'normalized_url' => 'https://example.com/test2',
            'slug' => 'def67890',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        Comment::create([
            'url_id' => $url1->id,
            'body' => 'Comment 1',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);

        Comment::create([
            'url_id' => $url2->id,
            'body' => 'Comment 2',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(200);
        $response->assertViewHas('totalUrls', 2);
        $response->assertViewHas('totalComments', 2);
    }

    /**
     * @test
     */
    public function it_can_flag_a_comment()
    {
        $user = User::factory()->create();

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

        $response = $this->actingAs($user)
            ->postJson("/admin/comments/{$comment->id}/flag");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Comment flagged successfully',
        ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'is_flagged' => true,
        ]);
    }

    /**
     * @test
     */
    public function it_can_dismiss_a_flagged_comment()
    {
        $user = User::factory()->create();

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
            'body' => 'This is a flagged comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/admin/comments/{$comment->id}/flag", [
                'is_flagged' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Flag dismissed successfully',
        ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'is_flagged' => false,
        ]);
    }

    /**
     * @test
     */
    public function it_can_delete_a_flagged_comment()
    {
        $user = User::factory()->create();

        $url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'comment_count' => 1,
        ]);

        $comment = Comment::create([
            'url_id' => $url->id,
            'body' => 'This is a flagged comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => true,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/admin/comments/{$comment->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Comment deleted successfully',
        ]);

        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id,
        ]);

        // Verify comment count was decremented
        $url->refresh();
        $this->assertEquals(0, $url->comment_count);
    }

    /**
     * @test
     */
    public function it_requires_authentication_to_flag_comments()
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

        $response = $this->postJson("/admin/comments/{$comment->id}/flag");

        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function it_requires_authentication_to_delete_comments()
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

        $response = $this->deleteJson("/admin/comments/{$comment->id}");

        $response->assertStatus(401);
    }

    /**
     * @test
     */
    public function it_displays_report_reason_for_flagged_comments()
    {
        $user = User::factory()->create();

        $url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'title' => 'Test Article',
            'comment_count' => 0,
        ]);

        $comment = Comment::create([
            'url_id' => $url->id,
            'body' => 'This is a flagged comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => true,
        ]);

        Report::create([
            'comment_id' => $comment->id,
            'reason' => 'spam',
            'ip_address' => '192.168.1.1',
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('spam');
    }

    /**
     * @test
     */
    public function it_displays_recent_reports_section()
    {
        $user = User::factory()->create();

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
            'body' => 'This is a reported comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);

        Report::create([
            'comment_id' => $comment->id,
            'reason' => 'hate',
            'ip_address' => '192.168.1.1',
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Recent Reports');
        $response->assertSee('This is a reported comment');
        $response->assertSee('hate');
    }
}

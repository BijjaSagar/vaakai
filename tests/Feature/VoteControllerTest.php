<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Url;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Url $url;
    private Comment $comment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->url = Url::create([
            'url_hash' => md5('https://example.com/test'),
            'original_url' => 'https://example.com/test',
            'normalized_url' => 'https://example.com/test',
            'slug' => 'abc12345',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        $this->comment = Comment::create([
            'url_id' => $this->url->id,
            'body' => 'Test comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);
    }

    /** @test */
    public function it_casts_a_new_like_vote()
    {
        $response = $this->postJson("/d/{$this->url->slug}/comments/{$this->comment->id}/vote", [
            'vote_type' => 'like',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'likes_count' => 1,
                'dislikes_count' => 0,
            ]);

        $this->assertDatabaseHas('votes', [
            'comment_id' => $this->comment->id,
            'vote_type' => 'like',
        ]);

        $this->comment->refresh();
        $this->assertEquals(1, $this->comment->likes_count);
        $this->assertEquals(0, $this->comment->dislikes_count);
    }

    /** @test */
    public function it_casts_a_new_dislike_vote()
    {
        $response = $this->postJson("/d/{$this->url->slug}/comments/{$this->comment->id}/vote", [
            'vote_type' => 'dislike',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'likes_count' => 0,
                'dislikes_count' => 1,
            ]);

        $this->assertDatabaseHas('votes', [
            'comment_id' => $this->comment->id,
            'vote_type' => 'dislike',
        ]);

        $this->comment->refresh();
        $this->assertEquals(0, $this->comment->likes_count);
        $this->assertEquals(1, $this->comment->dislikes_count);
    }

    /** @test */
    public function it_toggles_off_an_existing_like_vote()
    {
        // First, cast a like vote
        Vote::create([
            'comment_id' => $this->comment->id,
            'ip_address' => '127.0.0.1',
            'vote_type' => 'like',
        ]);
        $this->comment->update(['likes_count' => 1]);

        // Toggle off by voting like again
        $response = $this->postJson("/d/{$this->url->slug}/comments/{$this->comment->id}/vote", [
            'vote_type' => 'like',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'likes_count' => 0,
                'dislikes_count' => 0,
            ]);

        $this->assertDatabaseMissing('votes', [
            'comment_id' => $this->comment->id,
            'ip_address' => '127.0.0.1',
        ]);

        $this->comment->refresh();
        $this->assertEquals(0, $this->comment->likes_count);
    }

    /** @test */
    public function it_toggles_off_an_existing_dislike_vote()
    {
        // First, cast a dislike vote
        Vote::create([
            'comment_id' => $this->comment->id,
            'ip_address' => '127.0.0.1',
            'vote_type' => 'dislike',
        ]);
        $this->comment->update(['dislikes_count' => 1]);

        // Toggle off by voting dislike again
        $response = $this->postJson("/d/{$this->url->slug}/comments/{$this->comment->id}/vote", [
            'vote_type' => 'dislike',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'likes_count' => 0,
                'dislikes_count' => 0,
            ]);

        $this->assertDatabaseMissing('votes', [
            'comment_id' => $this->comment->id,
            'ip_address' => '127.0.0.1',
        ]);

        $this->comment->refresh();
        $this->assertEquals(0, $this->comment->dislikes_count);
    }

    /** @test */
    public function it_switches_from_like_to_dislike()
    {
        // First, cast a like vote
        Vote::create([
            'comment_id' => $this->comment->id,
            'ip_address' => '127.0.0.1',
            'vote_type' => 'like',
        ]);
        $this->comment->update(['likes_count' => 1]);

        // Switch to dislike
        $response = $this->postJson("/d/{$this->url->slug}/comments/{$this->comment->id}/vote", [
            'vote_type' => 'dislike',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'likes_count' => 0,
                'dislikes_count' => 1,
            ]);

        $this->assertDatabaseHas('votes', [
            'comment_id' => $this->comment->id,
            'ip_address' => '127.0.0.1',
            'vote_type' => 'dislike',
        ]);

        $this->comment->refresh();
        $this->assertEquals(0, $this->comment->likes_count);
        $this->assertEquals(1, $this->comment->dislikes_count);
    }

    /** @test */
    public function it_switches_from_dislike_to_like()
    {
        // First, cast a dislike vote
        Vote::create([
            'comment_id' => $this->comment->id,
            'ip_address' => '127.0.0.1',
            'vote_type' => 'dislike',
        ]);
        $this->comment->update(['dislikes_count' => 1]);

        // Switch to like
        $response = $this->postJson("/d/{$this->url->slug}/comments/{$this->comment->id}/vote", [
            'vote_type' => 'like',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'likes_count' => 1,
                'dislikes_count' => 0,
            ]);

        $this->assertDatabaseHas('votes', [
            'comment_id' => $this->comment->id,
            'ip_address' => '127.0.0.1',
            'vote_type' => 'like',
        ]);

        $this->comment->refresh();
        $this->assertEquals(1, $this->comment->likes_count);
        $this->assertEquals(0, $this->comment->dislikes_count);
    }

    /** @test */
    public function it_requires_vote_type()
    {
        $response = $this->postJson("/d/{$this->url->slug}/comments/{$this->comment->id}/vote", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vote_type']);
    }

    /** @test */
    public function it_validates_vote_type_must_be_like_or_dislike()
    {
        $response = $this->postJson("/d/{$this->url->slug}/comments/{$this->comment->id}/vote", [
            'vote_type' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vote_type']);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_comment()
    {
        $response = $this->postJson("/d/{$this->url->slug}/comments/99999/vote", [
            'vote_type' => 'like',
        ]);

        $response->assertStatus(404);
    }
}

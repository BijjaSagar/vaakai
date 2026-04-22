<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_returns_404_for_unknown_slug()
    {
        $response = $this->get('/d/unknown123');

        $response->assertStatus(404);
    }

    /**
     * @test
     */
    public function it_displays_url_metadata()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/article'),
            'original_url' => 'https://example.com/article',
            'normalized_url' => 'https://example.com/article',
            'slug' => 'abc12345',
            'title' => 'Test Article Title',
            'description' => 'This is a test article description',
            'thumbnail_url' => 'https://example.com/image.jpg',
            'domain' => 'example.com',
            'og_fetched_at' => now(),
            'comment_count' => 0,
        ]);

        $response = $this->get("/d/{$url->slug}");

        $response->assertStatus(200);
        $response->assertSee('Test Article Title');
        $response->assertSee('This is a test article description');
        $response->assertSee('example.com');
        $response->assertSee('0 Comments');
    }

    /**
     * @test
     */
    public function it_displays_comment_list()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/article'),
            'original_url' => 'https://example.com/article',
            'normalized_url' => 'https://example.com/article',
            'slug' => 'abc12345',
            'title' => 'Test Article',
            'domain' => 'example.com',
            'comment_count' => 2,
        ]);

        Comment::create([
            'url_id' => $url->id,
            'guest_name' => 'John Doe',
            'body' => 'This is a great article!',
            'sentiment' => 'positive',
            'ip_address' => '127.0.0.1',
        ]);

        Comment::create([
            'url_id' => $url->id,
            'guest_name' => 'Jane Smith',
            'body' => 'I disagree with this.',
            'sentiment' => 'negative',
            'ip_address' => '127.0.0.2',
        ]);

        $response = $this->get("/d/{$url->slug}");

        $response->assertStatus(200);
        $response->assertSee('John Doe');
        $response->assertSee('This is a great article!');
        $response->assertSee('Jane Smith');
        $response->assertSee('I disagree with this.');
        $response->assertSee('2 Comments');
    }

    /**
     * @test
     */
    public function it_displays_nested_replies()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/article'),
            'original_url' => 'https://example.com/article',
            'normalized_url' => 'https://example.com/article',
            'slug' => 'abc12345',
            'title' => 'Test Article',
            'domain' => 'example.com',
            'comment_count' => 2,
        ]);

        $parentComment = Comment::create([
            'url_id' => $url->id,
            'guest_name' => 'Parent User',
            'body' => 'This is the parent comment',
            'sentiment' => 'neutral',
            'ip_address' => '127.0.0.1',
        ]);

        Comment::create([
            'url_id' => $url->id,
            'parent_id' => $parentComment->id,
            'guest_name' => 'Reply User',
            'body' => 'This is a reply to the parent',
            'sentiment' => 'positive',
            'ip_address' => '127.0.0.2',
        ]);

        $response = $this->get("/d/{$url->slug}");

        $response->assertStatus(200);
        $response->assertSee('Parent User');
        $response->assertSee('This is the parent comment');
        $response->assertSee('Reply User');
        $response->assertSee('This is a reply to the parent');
    }

    /**
     * @test
     */
    public function it_shows_empty_state_when_no_comments()
    {
        $url = Url::create([
            'url_hash' => md5('https://example.com/article'),
            'original_url' => 'https://example.com/article',
            'normalized_url' => 'https://example.com/article',
            'slug' => 'abc12345',
            'title' => 'Test Article',
            'domain' => 'example.com',
            'comment_count' => 0,
        ]);

        $response = $this->get("/d/{$url->slug}");

        $response->assertStatus(200);
        $response->assertSee('No comments yet');
    }
}

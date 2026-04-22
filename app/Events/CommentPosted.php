<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Comment $comment;
    public string $slug;

    /**
     * Create a new event instance.
     */
    public function __construct(Comment $comment, string $slug)
    {
        $this->comment = $comment;
        $this->slug = $slug;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('discussion.' . $this->slug);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'CommentPosted';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->comment->id,
            'guest_name' => $this->comment->guest_name,
            'body' => $this->comment->body,
            'sentiment' => $this->comment->sentiment,
            'likes_count' => $this->comment->likes_count,
            'dislikes_count' => $this->comment->dislikes_count,
            'created_at' => $this->comment->created_at->toISOString(),
            'parent_id' => $this->comment->parent_id,
        ];
    }
}

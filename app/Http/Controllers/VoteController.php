<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Vote;
use Illuminate\Http\Request;

class VoteController extends Controller
{
    public function toggle(Request $request, string $slug, int $id)
    {
        $request->validate([
            'vote_type' => 'required|in:like,dislike',
        ]);

        $comment = Comment::findOrFail($id);
        $voteType = $request->input('vote_type');
        $ipAddress = $request->ip();

        $existing = Vote::where('comment_id', $id)
            ->where('ip_address', $ipAddress)
            ->first();

        if (!$existing) {
            // No vote exists → cast new vote
            Vote::create([
                'comment_id' => $id,
                'ip_address' => $ipAddress,
                'vote_type' => $voteType,
            ]);
            
            if ($voteType === 'like') {
                $comment->increment('likes_count');
            } else {
                $comment->increment('dislikes_count');
            }
        } elseif ($existing->vote_type === $voteType) {
            // Same vote → remove vote
            $existing->delete();
            
            if ($voteType === 'like') {
                $comment->decrement('likes_count');
            } else {
                $comment->decrement('dislikes_count');
            }
        } else {
            // Opposite vote → switch vote
            $existing->update(['vote_type' => $voteType]);
            
            if ($voteType === 'like') {
                $comment->increment('likes_count');
                $comment->decrement('dislikes_count');
            } else {
                $comment->increment('dislikes_count');
                $comment->decrement('likes_count');
            }
        }

        $comment->refresh();

        return response()->json([
            'likes_count' => $comment->likes_count,
            'dislikes_count' => $comment->dislikes_count,
        ]);
    }
}

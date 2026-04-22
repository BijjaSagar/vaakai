<?php

namespace App\Http\Controllers;

use App\Events\CommentPosted;
use App\Models\Comment;
use App\Models\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Get paginated comments with sorting
     * 
     * GET /d/{slug}/comments?sort=newest|top|positive|negative
     */
    public function index(Request $request, string $slug)
    {
        // Find the URL by slug
        $url = Url::where('slug', $slug)->firstOrFail();
        
        // Get sort mode (default: newest)
        $sortMode = $request->query('sort', 'newest');
        
        // Build query for top-level comments only
        $query = Comment::where('url_id', $url->id)
            ->whereNull('parent_id');
        
        // Apply sorting/filtering based on mode
        switch ($sortMode) {
            case 'top':
                $query->orderByRaw('(likes_count - dislikes_count) DESC');
                break;
            case 'positive':
                $query->where('sentiment', 'positive')
                    ->orderBy('created_at', 'DESC');
                break;
            case 'negative':
                $query->where('sentiment', 'negative')
                    ->orderBy('created_at', 'DESC');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'DESC');
                break;
        }
        
        // Paginate results
        $comments = $query->paginate(20);
        
        return response()->json($comments);
    }

    /**
     * Store a new comment
     * 
     * POST /d/{slug}/comment
     */
    public function store(Request $request, string $slug)
    {
        // Find the URL by slug
        $url = Url::where('slug', $slug)->firstOrFail();
        
        // Validate input
        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:2000',
            'sentiment' => 'required|in:positive,negative,neutral',
            'guest_name' => 'nullable|string|max:80',
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        // If parent_id is provided, validate reply depth constraint
        if ($request->filled('parent_id')) {
            $parentComment = Comment::find($request->input('parent_id'));
            
            // Ensure parent comment exists and has parent_id = null (one level only)
            if (!$parentComment || $parentComment->parent_id !== null) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'parent_id' => ['Replies are only allowed one level deep. Cannot reply to a reply.'],
                    ],
                ], 422);
            }
        }
        
        // Get IP address
        $ipAddress = $request->ip();
        
        // Create comment
        $comment = Comment::create([
            'url_id' => $url->id,
            'parent_id' => $request->input('parent_id'),
            'guest_name' => $request->input('guest_name'),
            'body' => $request->input('body'),
            'sentiment' => $request->input('sentiment'),
            'ip_address' => $ipAddress,
            'likes_count' => 0,
            'dislikes_count' => 0,
            'is_flagged' => false,
        ]);
        
        // Increment comment_count on the URL
        $url->increment('comment_count');
        
        // Invalidate trending discussions cache
        Cache::forget('trending:discussions');
        
        // Fire CommentPosted event for real-time broadcast
        event(new CommentPosted($comment, $slug));
        
        return response()->json([
            'success' => true,
            'message' => 'Comment posted successfully.',
            'comment' => $comment,
        ], 201);
    }
}

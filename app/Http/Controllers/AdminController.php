<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Display the admin dashboard with flagged comments and platform stats.
     */
    public function index()
    {
        $flaggedComments = Comment::where('is_flagged', true)
            ->with(['url', 'reports' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalUrls = Url::count();
        $totalComments = Comment::count();

        $recentReports = DB::table('reports')
            ->join('comments', 'reports.comment_id', '=', 'comments.id')
            ->select('reports.*', 'comments.body as comment_body')
            ->orderBy('reports.created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'flaggedComments',
            'totalUrls',
            'totalComments',
            'recentReports'
        ));
    }

    /**
     * Flag a comment as inappropriate.
     */
    public function flagComment(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        
        // Check if we're dismissing the flag
        $isFlagged = $request->input('is_flagged', true);
        $comment->is_flagged = $isFlagged;
        $comment->save();

        return response()->json([
            'success' => true,
            'message' => $isFlagged ? 'Comment flagged successfully' : 'Flag dismissed successfully',
        ]);
    }

    /**
     * Delete a flagged comment.
     */
    public function deleteComment($id)
    {
        $comment = Comment::findOrFail($id);
        
        // Decrement the comment count on the associated URL
        $url = $comment->url;
        if ($url) {
            $url->decrement('comment_count');
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully',
        ]);
    }
}

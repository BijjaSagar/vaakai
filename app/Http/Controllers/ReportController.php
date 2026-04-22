<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function store(Request $request, string $slug, int $id)
    {
        $request->validate([
            'reason' => 'required|in:spam,hate,fake,other',
        ]);

        $comment = Comment::findOrFail($id);

        DB::table('reports')->insert([
            'comment_id' => $id,
            'reason' => $request->input('reason'),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report submitted successfully',
        ]);
    }
}

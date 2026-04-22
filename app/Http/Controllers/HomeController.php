<?php

namespace App\Http\Controllers;

use App\Models\Url;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /**
     * Display the homepage with trending discussions
     * 
     * GET /
     */
    public function index()
    {
        // Cache trending discussions for 5 minutes
        $trendingDiscussions = Cache::remember('trending:discussions', 300, function () {
            return Url::orderBy('comment_count', 'desc')
                ->limit(10)
                ->get();
        });

        return view('pages.home', [
            'trendingDiscussions' => $trendingDiscussions,
        ]);
    }
}

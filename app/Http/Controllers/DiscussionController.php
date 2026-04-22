<?php

namespace App\Http\Controllers;

use App\Jobs\FetchUrlMetadata;
use App\Models\Url;
use App\Services\UrlNormalizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiscussionController extends Controller
{
    public function __construct(
        private UrlNormalizerService $urlNormalizer
    ) {}

    /**
     * Handle URL submission
     * 
     * POST /discuss
     */
    public function submit(Request $request)
    {
        // Validate URL
        $validator = Validator::make($request->all(), [
            'url' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Check if URL starts with http:// or https://
                    if (!preg_match('/^https?:\/\/.+/i', $value)) {
                        $fail('The URL must start with http:// or https://');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $originalUrl = $request->input('url');
        
        // Normalize URL and compute hash
        $normalizedUrl = $this->urlNormalizer->normalize($originalUrl);
        $urlHash = $this->urlNormalizer->hash($normalizedUrl);
        
        // Check if URL already exists
        $existingUrl = Url::where('url_hash', $urlHash)->first();
        
        if ($existingUrl) {
            // Redirect to existing discussion
            return response()->json([
                'redirect' => "/d/{$existingUrl->slug}",
            ], 200);
        }
        
        // Generate unique slug
        $slug = $this->generateUniqueSlug($urlHash);
        
        // Parse domain from normalized URL
        $parsedUrl = parse_url($normalizedUrl);
        $domain = $parsedUrl['host'] ?? '';
        
        // Create new URL record
        $url = Url::create([
            'url_hash' => $urlHash,
            'original_url' => $originalUrl,
            'normalized_url' => $normalizedUrl,
            'slug' => $slug,
            'domain' => $domain,
            'comment_count' => 0,
        ]);
        
        // Dispatch FetchUrlMetadata job to fetch OG metadata in background
        FetchUrlMetadata::dispatch($url);
        
        // Redirect to new discussion
        return response()->json([
            'redirect' => "/d/{$url->slug}",
        ], 201);
    }

    /**
     * Show discussion page
     * 
     * GET /d/{slug}
     */
    public function show(Request $request, string $slug)
    {
        // Get sort mode (default: newest)
        $sortMode = $request->query('sort', 'newest');
        
        // Load URL by slug with top-level comments and their replies
        $url = Url::where('slug', $slug)
            ->with(['comments' => function ($query) use ($sortMode) {
                $query->whereNull('parent_id')
                    ->with('replies');
                
                // Apply sorting based on mode
                switch ($sortMode) {
                    case 'top':
                        $query->orderByRaw('(likes_count - dislikes_count) DESC');
                        break;
                    case 'positive':
                        $query->where('sentiment', 'positive')
                            ->orderBy('created_at', 'desc');
                        break;
                    case 'negative':
                        $query->where('sentiment', 'negative')
                            ->orderBy('created_at', 'desc');
                        break;
                    case 'newest':
                    default:
                        $query->orderBy('created_at', 'desc');
                        break;
                }
            }])
            ->firstOrFail();

        return view('pages.discussion', [
            'url' => $url,
        ]);
    }

    /**
     * Generate a unique slug from the URL hash
     * 
     * Takes 8-character windows from the hash until a unique slug is found
     */
    private function generateUniqueSlug(string $hash): string
    {
        $offset = 0;
        $maxAttempts = 4; // 32-char hash / 8 = 4 possible slugs
        
        do {
            $slug = substr($hash, $offset, 8);
            $offset += 8;
            $maxAttempts--;
            
            if (!Url::where('slug', $slug)->exists()) {
                return $slug;
            }
        } while ($maxAttempts > 0 && $offset < 32);
        
        // Fallback: append random string if all 8-char windows are taken
        return substr($hash, 0, 8) . bin2hex(random_bytes(2));
    }
}

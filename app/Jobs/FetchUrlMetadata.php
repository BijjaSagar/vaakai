<?php

namespace App\Jobs;

use App\Models\Url;
use App\Services\OgScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class FetchUrlMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Url $url
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OgScraperService $scraper): void
    {
        // Fetch metadata using OgScraperService
        $metadata = $scraper->fetch($this->url->normalized_url);
        
        // Update the URL record with fetched metadata
        $this->url->update([
            'title' => $metadata['title'],
            'description' => $metadata['description'],
            'thumbnail_url' => $metadata['thumbnail_url'],
            'domain' => $metadata['domain'],
            'og_fetched_at' => $metadata['og_fetched_at'],
        ]);
        
        // Cache the result in Redis with 7-day TTL
        $cacheKey = "og:{$this->url->url_hash}";
        $cacheTtl = 60 * 24 * 7; // 7 days in minutes
        
        Cache::put($cacheKey, [
            'title' => $metadata['title'],
            'description' => $metadata['description'],
            'thumbnail_url' => $metadata['thumbnail_url'],
            'domain' => $metadata['domain'],
            'og_fetched_at' => $metadata['og_fetched_at']->toIso8601String(),
        ], $cacheTtl);
    }
}

<?php

namespace Database\Factories;

use App\Models\Url;
use Illuminate\Database\Eloquent\Factories\Factory;

class UrlFactory extends Factory
{
    protected $model = Url::class;

    public function definition(): array
    {
        $normalizedUrl = 'https://' . $this->faker->domainName() . '/' . $this->faker->slug;
        $urlHash = md5($normalizedUrl);
        $slug = substr($urlHash, 0, 8);

        return [
            'url_hash' => $urlHash,
            'original_url' => $normalizedUrl,
            'normalized_url' => $normalizedUrl,
            'slug' => $slug,
            'domain' => parse_url($normalizedUrl, PHP_URL_HOST),
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'thumbnail_url' => $this->faker->imageUrl(),
            'og_fetched_at' => now(),
            'comment_count' => 0,
        ];
    }
}

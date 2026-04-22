<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Url;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'url_id' => Url::factory(),
            'parent_id' => null,
            'guest_name' => $this->faker->name,
            'body' => $this->faker->paragraph,
            'sentiment' => $this->faker->randomElement(['positive', 'negative', 'neutral']),
            'likes_count' => 0,
            'dislikes_count' => 0,
            'ip_address' => $this->faker->ipv4,
            'is_flagged' => false,
        ];
    }
}

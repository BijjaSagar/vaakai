<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Url extends Model
{
    use HasFactory;

    protected $fillable = [
        'url_hash',
        'original_url',
        'normalized_url',
        'slug',
        'title',
        'description',
        'thumbnail_url',
        'domain',
        'og_fetched_at',
        'comment_count',
    ];

    protected $casts = [
        'og_fetched_at' => 'datetime',
        'comment_count' => 'integer',
    ];

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}

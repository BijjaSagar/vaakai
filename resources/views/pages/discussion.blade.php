@extends('layouts.app')

@section('title', $url->title ?? $url->domain . ' - SpeakSpace')

@section('content')
<div>
    <!-- URL Preview Card -->
    <x-url-preview :url="$url" />

    <!-- Sort Controls -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">
                {{ $url->comment_count }} {{ Str::plural('Comment', $url->comment_count) }}
            </h2>
            
            <div class="flex gap-2">
                <a 
                    href="/d/{{ $url->slug }}?sort=newest"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition {{ request('sort', 'newest') === 'newest' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}"
                >
                    Newest
                </a>
                <a 
                    href="/d/{{ $url->slug }}?sort=top"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition {{ request('sort') === 'top' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}"
                >
                    Top
                </a>
                <a 
                    href="/d/{{ $url->slug }}?sort=positive"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition {{ request('sort') === 'positive' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}"
                >
                    Positive
                </a>
                <a 
                    href="/d/{{ $url->slug }}?sort=negative"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition {{ request('sort') === 'negative' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}"
                >
                    Negative
                </a>
            </div>
        </div>
    </div>

    <!-- Comment Form -->
    <div class="mb-6">
        <x-comment-form :slug="$url->slug" />
    </div>

    <!-- Comments List -->
    <div class="space-y-4">
        @forelse($url->comments as $comment)
            <x-comment-card :comment="$comment" :slug="$url->slug" />
        @empty
            <div class="bg-white rounded-lg shadow-sm p-8 text-center">
                <p class="text-gray-500">No comments yet. Be the first to share your thoughts!</p>
            </div>
        @endforelse
    </div>
</div>
@endsection

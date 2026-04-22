@extends('layouts.app')

@section('title', 'SpeakSpace - Discuss Any URL')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">Discuss Any URL</h1>
        <p class="text-lg text-gray-600">Paste any public URL to start or join a discussion</p>
    </div>

    <div x-data="{ 
        url: '', 
        loading: false, 
        error: null,
        submitUrl() {
            this.loading = true;
            this.error = null;
            
            fetch('/discuss', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ url: this.url })
            })
            .then(response => response.json())
            .then(data => {
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else if (data.errors) {
                    this.error = Object.values(data.errors)[0][0];
                    this.loading = false;
                }
            })
            .catch(err => {
                this.error = 'An error occurred. Please try again.';
                this.loading = false;
            });
        }
    }" class="bg-white rounded-lg shadow-md p-8">
        <form @submit.prevent="submitUrl">
            <div class="mb-4">
                <label for="url" class="block text-sm font-medium text-gray-700 mb-2">
                    Enter URL
                </label>
                <input 
                    type="text" 
                    id="url"
                    x-model="url"
                    placeholder="https://example.com/article"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                >
            </div>

            <div x-show="error" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-600" x-text="error"></p>
            </div>

            <button 
                type="submit"
                :disabled="loading || !url"
                class="w-full bg-blue-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition"
            >
                <span x-show="!loading">Start Discussion</span>
                <span x-show="loading">Loading...</span>
            </button>
        </form>
    </div>

    @if($trendingDiscussions->isNotEmpty())
    <div class="mt-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Trending Discussions</h2>
        <div class="space-y-4">
            @foreach($trendingDiscussions as $discussion)
            <a href="/d/{{ $discussion->slug }}" class="block bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            {{ $discussion->title ?? $discussion->domain }}
                        </h3>
                        @if($discussion->description)
                        <p class="text-sm text-gray-600 mb-2 line-clamp-2">
                            {{ $discussion->description }}
                        </p>
                        @endif
                        <div class="flex items-center text-sm text-gray-500">
                            <span class="mr-4">{{ $discussion->domain }}</span>
                            <span>{{ $discussion->comment_count }} {{ Str::plural('comment', $discussion->comment_count) }}</span>
                        </div>
                    </div>
                    @if($discussion->thumbnail_url)
                    <img src="{{ $discussion->thumbnail_url }}" alt="{{ $discussion->title }}" class="w-20 h-20 object-cover rounded ml-4">
                    @endif
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

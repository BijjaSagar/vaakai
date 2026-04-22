@props(['url'])

<div class="bg-white rounded-lg shadow-sm p-6 mb-6">
    <div class="flex gap-4">
        @if($url->thumbnail_url)
            <div class="flex-shrink-0">
                <img 
                    src="{{ $url->thumbnail_url }}" 
                    alt="{{ $url->title ?? $url->domain }}"
                    class="w-32 h-32 object-cover rounded-lg"
                >
            </div>
        @endif
        
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">
                {{ $url->title ?? $url->domain }}
            </h1>
            
            @if($url->description)
                <p class="text-gray-600 mb-3 line-clamp-2">
                    {{ $url->description }}
                </p>
            @endif
            
            <div class="flex items-center gap-4 text-sm text-gray-500">
                <span class="flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                    </svg>
                    {{ $url->domain }}
                </span>
                
                <a 
                    href="{{ $url->normalized_url }}" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    class="text-blue-600 hover:text-blue-700 hover:underline"
                >
                    Visit original →
                </a>
            </div>
        </div>
    </div>
</div>

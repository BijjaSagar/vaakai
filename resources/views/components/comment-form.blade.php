@props(['slug', 'parentId' => null, 'compact' => false])

<div 
    x-data="{ 
        guestName: '', 
        body: '', 
        sentiment: 'neutral',
        loading: false,
        error: null,
        submitComment() {
            this.loading = true;
            this.error = null;
            
            fetch('/d/{{ $slug }}/comment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ 
                    guest_name: this.guestName,
                    body: this.body,
                    sentiment: this.sentiment,
                    parent_id: {{ $parentId ?? 'null' }}
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
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
    }"
    class="{{ $compact ? 'bg-gray-50' : 'bg-white' }} rounded-lg {{ $compact ? 'p-3' : 'shadow-sm p-6' }}"
>
    <form @submit.prevent="submitComment">
        @if(!$compact)
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Add a Comment</h3>
        @endif

        <div class="space-y-4">
            <div>
                <label for="guest_name_{{ $parentId ?? 'main' }}" class="block text-sm font-medium text-gray-700 mb-1">
                    Display Name (optional)
                </label>
                <input 
                    type="text" 
                    id="guest_name_{{ $parentId ?? 'main' }}"
                    x-model="guestName"
                    maxlength="80"
                    placeholder="Anonymous"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $compact ? 'text-sm' : '' }}"
                    :disabled="loading"
                >
            </div>

            <div>
                <label for="body_{{ $parentId ?? 'main' }}" class="block text-sm font-medium text-gray-700 mb-1">
                    Comment
                </label>
                <textarea 
                    id="body_{{ $parentId ?? 'main' }}"
                    x-model="body"
                    rows="{{ $compact ? '3' : '4' }}"
                    maxlength="2000"
                    placeholder="Share your thoughts..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $compact ? 'text-sm' : '' }}"
                    :disabled="loading"
                    required
                ></textarea>
                <p class="text-xs text-gray-500 mt-1" x-text="`${body.length}/2000 characters`"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Sentiment
                </label>
                <div class="flex gap-2">
                    <button 
                        type="button"
                        @click="sentiment = 'positive'"
                        :class="sentiment === 'positive' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-white border-gray-300 text-gray-700'"
                        class="flex-1 px-4 py-2 border-2 rounded-lg font-medium transition {{ $compact ? 'text-sm' : '' }}"
                        :disabled="loading"
                    >
                        👍 Positive
                    </button>
                    <button 
                        type="button"
                        @click="sentiment = 'neutral'"
                        :class="sentiment === 'neutral' ? 'bg-gray-100 border-gray-500 text-gray-700' : 'bg-white border-gray-300 text-gray-700'"
                        class="flex-1 px-4 py-2 border-2 rounded-lg font-medium transition {{ $compact ? 'text-sm' : '' }}"
                        :disabled="loading"
                    >
                        😐 Neutral
                    </button>
                    <button 
                        type="button"
                        @click="sentiment = 'negative'"
                        :class="sentiment === 'negative' ? 'bg-red-100 border-red-500 text-red-700' : 'bg-white border-gray-300 text-gray-700'"
                        class="flex-1 px-4 py-2 border-2 rounded-lg font-medium transition {{ $compact ? 'text-sm' : '' }}"
                        :disabled="loading"
                    >
                        👎 Negative
                    </button>
                </div>
            </div>

            <div x-show="error" class="p-3 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-600" x-text="error"></p>
            </div>

            <button 
                type="submit"
                :disabled="loading || !body"
                class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition {{ $compact ? 'text-sm' : '' }}"
            >
                <span x-show="!loading">{{ $compact ? 'Reply' : 'Post Comment' }}</span>
                <span x-show="loading">Posting...</span>
            </button>
        </div>
    </form>
</div>

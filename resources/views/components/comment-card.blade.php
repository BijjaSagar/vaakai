@props(['comment', 'slug'])

<div class="bg-white rounded-lg shadow-sm p-4" x-data="{ 
    showReplyForm: false,
    showReportModal: false,
    reportReason: '',
    likesCount: {{ $comment->likes_count }},
    dislikesCount: {{ $comment->dislikes_count }},
    async vote(voteType) {
        try {
            const response = await fetch('/d/{{ $slug }}/comments/{{ $comment->id }}/vote', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ vote_type: voteType })
            });
            const data = await response.json();
            this.likesCount = data.likes_count;
            this.dislikesCount = data.dislikes_count;
        } catch (error) {
            console.error('Vote failed:', error);
        }
    },
    async submitReport() {
        if (!this.reportReason) {
            alert('Please select a reason');
            return;
        }
        try {
            const response = await fetch('/d/{{ $slug }}/comments/{{ $comment->id }}/report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({ reason: this.reportReason })
            });
            const data = await response.json();
            if (data.success) {
                alert('Report submitted successfully');
                this.showReportModal = false;
                this.reportReason = '';
            }
        } catch (error) {
            console.error('Report failed:', error);
            alert('Failed to submit report');
        }
    }
}">
    <div class="flex gap-3">
        <!-- Vote buttons -->
        <div class="flex flex-col items-center gap-1">
            <button @click="vote('like')" class="text-gray-400 hover:text-green-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                </svg>
            </button>
            <span class="text-sm font-medium text-gray-700" x-text="likesCount - dislikesCount">
            </span>
            <button @click="vote('dislike')" class="text-gray-400 hover:text-red-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
        </div>

        <!-- Comment content -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-2">
                <span class="font-medium text-gray-900">
                    {{ $comment->guest_name ?: 'Anonymous' }}
                </span>
                
                <!-- Sentiment badge -->
                @if($comment->sentiment === 'positive')
                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded">
                        Positive
                    </span>
                @elseif($comment->sentiment === 'negative')
                    <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-medium rounded">
                        Negative
                    </span>
                @else
                    <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs font-medium rounded">
                        Neutral
                    </span>
                @endif
                
                <span class="text-xs text-gray-500">
                    {{ $comment->created_at->diffForHumans() }}
                </span>
            </div>

            <p class="text-gray-700 mb-3 whitespace-pre-wrap">{{ $comment->body }}</p>

            <div class="flex items-center gap-4">
                <button 
                    @click="showReplyForm = !showReplyForm"
                    class="text-sm text-gray-500 hover:text-blue-600 transition"
                >
                    Reply
                </button>
                <button 
                    @click="showReportModal = true"
                    class="text-sm text-gray-500 hover:text-red-600 transition"
                >
                    Report
                </button>
            </div>

            <!-- Reply form -->
            <div x-show="showReplyForm" x-cloak class="mt-4">
                <x-comment-form :slug="$slug" :parentId="$comment->id" :compact="true" />
            </div>

            <!-- Report modal -->
            <div x-show="showReportModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showReportModal = false">
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <h3 class="text-lg font-semibold mb-4">Report Comment</h3>
                    <p class="text-sm text-gray-600 mb-4">Please select a reason for reporting this comment:</p>
                    
                    <div class="space-y-2 mb-6">
                        <label class="flex items-center">
                            <input type="radio" x-model="reportReason" value="spam" class="mr-2">
                            <span class="text-sm">Spam</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" x-model="reportReason" value="hate" class="mr-2">
                            <span class="text-sm">Hate speech</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" x-model="reportReason" value="fake" class="mr-2">
                            <span class="text-sm">Fake information</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" x-model="reportReason" value="other" class="mr-2">
                            <span class="text-sm">Other</span>
                        </label>
                    </div>
                    
                    <div class="flex gap-3">
                        <button 
                            @click="submitReport()"
                            class="flex-1 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition"
                        >
                            Submit Report
                        </button>
                        <button 
                            @click="showReportModal = false; reportReason = ''"
                            class="flex-1 bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 transition"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Replies -->
            @if($comment->replies->count() > 0)
                <div class="mt-4 space-y-3 pl-4 border-l-2 border-gray-200">
                    @foreach($comment->replies as $reply)
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-medium text-gray-900 text-sm">
                                    {{ $reply->guest_name ?: 'Anonymous' }}
                                </span>
                                
                                @if($reply->sentiment === 'positive')
                                    <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded">
                                        Positive
                                    </span>
                                @elseif($reply->sentiment === 'negative')
                                    <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-medium rounded">
                                        Negative
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs font-medium rounded">
                                        Neutral
                                    </span>
                                @endif
                                
                                <span class="text-xs text-gray-500">
                                    {{ $reply->created_at->diffForHumans() }}
                                </span>
                            </div>
                            
                            <p class="text-gray-700 text-sm whitespace-pre-wrap">{{ $reply->body }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

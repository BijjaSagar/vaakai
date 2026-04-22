@extends('layouts.app')

@section('title', 'Admin Dashboard - SpeakSpace')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
        <p class="text-gray-600 mt-2">Manage flagged comments and monitor platform activity</p>
    </div>

    <!-- Platform Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500 mb-1">Total URLs</div>
            <div class="text-3xl font-bold text-gray-900">{{ number_format($totalUrls) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Comments</div>
            <div class="text-3xl font-bold text-gray-900">{{ number_format($totalComments) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm font-medium text-gray-500 mb-1">Flagged Comments</div>
            <div class="text-3xl font-bold text-red-600">{{ number_format($flaggedComments->count()) }}</div>
        </div>
    </div>

    <!-- Flagged Comments Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Flagged Comments</h2>
        </div>

        @if($flaggedComments->isEmpty())
            <div class="px-6 py-8 text-center text-gray-500">
                No flagged comments at this time.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($flaggedComments as $comment)
                            <tr x-data="{ deleting: false }">
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-md truncate">{{ $comment->body }}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        by {{ $comment->guest_name ?: 'Anonymous' }} on 
                                        <a href="/d/{{ $comment->url->slug }}" class="text-blue-600 hover:underline">
                                            {{ $comment->url->title }}
                                        </a>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($comment->reports->isNotEmpty())
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            {{ $comment->reports->first()->reason }}
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400">No reports</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $comment->ip_address }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $comment->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 text-sm font-medium">
                                    <button 
                                        @click="
                                            if (confirm('Are you sure you want to dismiss this flag?')) {
                                                fetch('/admin/comments/{{ $comment->id }}/flag', {
                                                    method: 'POST',
                                                    headers: {
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                        'Content-Type': 'application/json'
                                                    },
                                                    body: JSON.stringify({ is_flagged: false })
                                                }).then(() => location.reload());
                                            }
                                        "
                                        class="text-blue-600 hover:text-blue-900 mr-4"
                                    >
                                        Dismiss
                                    </button>
                                    <button 
                                        @click="
                                            if (confirm('Are you sure you want to delete this comment? This action cannot be undone.')) {
                                                deleting = true;
                                                fetch('/admin/comments/{{ $comment->id }}', {
                                                    method: 'DELETE',
                                                    headers: {
                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                                    }
                                                }).then(() => location.reload());
                                            }
                                        "
                                        :disabled="deleting"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        <span x-show="!deleting">Delete</span>
                                        <span x-show="deleting">Deleting...</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Recent Reports -->
    <div class="bg-white rounded-lg shadow overflow-hidden mt-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Recent Reports</h2>
        </div>

        @if($recentReports->isEmpty())
            <div class="px-6 py-8 text-center text-gray-500">
                No recent reports.
            </div>
        @else
            <div class="divide-y divide-gray-200">
                @foreach($recentReports as $report)
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="text-sm text-gray-900">{{ Str::limit($report->comment_body, 100) }}</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    Reported as <span class="font-medium">{{ $report->reason }}</span> 
                                    from {{ $report->ip_address }} 
                                    on {{ \Carbon\Carbon::parse($report->created_at)->format('M d, Y H:i') }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

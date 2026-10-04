@extends('layouts.app')

@section('title', 'Ticket #'.$ticket->id.' — Admin')

@section('content')
@include('admin._nav')
<div class="max-w-3xl mx-auto px-4 py-8">
    <a href="{{ route('hmkr.support.index') }}" class="text-sm text-indigo-700 hover:underline">← Back to tickets</a>
    <h1 class="text-2xl font-bold mt-2 mb-1">{{ $ticket->subject }}</h1>
    <p class="text-sm text-slate-500 mb-6">From {{ $ticket->user?->username }} · {{ $ticket->created_at->format('d M Y, h:i A') }} ·
        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-200">{{ $ticket->status }}</span>
    </p>

    <div class="space-y-3 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-slate-500 mb-1">{{ $ticket->user?->username }} (player)</p>
            <p class="text-sm whitespace-pre-wrap">{{ $ticket->message }}</p>
        </div>
        @foreach ($ticket->replies as $reply)
            <div class="rounded-lg shadow p-4 {{ $reply->is_bot ? 'bg-amber-50 border border-amber-200' : 'bg-white' }}">
                <p class="text-xs text-slate-500 mb-1">
                    @if ($reply->is_bot)
                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-200 text-amber-900">Automated assistant</span>
                    @else
                        Support ({{ $reply->user_id }})
                    @endif
                    · {{ $reply->created_at->format('d M, h:i A') }}
                </p>
                <p class="text-sm whitespace-pre-wrap">{{ $reply->body }}</p>
            </div>
        @endforeach
    </div>

    @if ($ticket->status !== 'closed')
        <div class="bg-white rounded-lg shadow p-6 mb-4">
            <h2 class="font-bold mb-3">Reply</h2>
            <form method="POST" action="{{ route('hmkr.support.reply', $ticket) }}">
                @csrf
                <textarea name="body" rows="4" required maxlength="5000" class="border rounded px-3 py-2 text-sm w-full" placeholder="Write your reply…"></textarea>
                <button class="mt-2 bg-indigo-700 text-white px-4 py-2 rounded text-sm">Send reply</button>
            </form>
        </div>
        <div class="flex gap-4">
            <form method="POST" action="{{ route('hmkr.support.close', $ticket) }}">@csrf<button class="text-sm text-slate-600 hover:underline">Close ticket</button></form>
        </div>
    @else
        <form method="POST" action="{{ route('hmkr.support.reopen', $ticket) }}">@csrf<button class="text-sm text-indigo-700 hover:underline">Re-open ticket</button></form>
    @endif
</div>
@endsection

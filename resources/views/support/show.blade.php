@extends('layouts.app')

@section('title', 'Ticket — LudoVerse')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-12">
    <a href="{{ route('support.index') }}" class="text-sm text-indigo-700 hover:underline">← All tickets</a>
    <h1 class="text-2xl font-bold mt-2 mb-1">{{ $ticket->subject }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ $ticket->created_at->format('d M Y, h:i A') }} ·
        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-200">{{ $ticket->status }}</span>
    </p>

    <div class="space-y-3">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-slate-500 mb-1">You</p>
            <p class="text-sm whitespace-pre-wrap">{{ $ticket->message }}</p>
        </div>
        @foreach ($ticket->replies as $reply)
            <div class="rounded-lg shadow p-4 {{ $reply->is_bot ? 'bg-amber-50 border border-amber-200' : 'bg-white' }}">
                <p class="text-xs text-slate-500 mb-1">
                    @if ($reply->is_bot)
                        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-200 text-amber-900">Automated assistant</span>
                    @else
                        Support team
                    @endif
                    · {{ $reply->created_at->format('d M, h:i A') }}
                </p>
                <p class="text-sm whitespace-pre-wrap">{{ $reply->body }}</p>
            </div>
        @endforeach
    </div>
</div>
@endsection

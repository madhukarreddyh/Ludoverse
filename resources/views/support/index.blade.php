@extends('layouts.app')

@section('title', 'Support — LudoVerse')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Support</h1>
        <a href="{{ route('support.create') }}" class="bg-indigo-700 text-white px-4 py-2 rounded text-sm">New ticket</a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="text-left px-4 py-2">Subject</th>
                    <th class="text-left px-4 py-2">Status</th>
                    <th class="text-left px-4 py-2">Opened</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2"><a href="{{ route('support.show', $ticket) }}" class="text-indigo-700 hover:underline">{{ $ticket->subject }}</a></td>
                        <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold {{ $ticket->status === 'open' ? 'bg-red-100 text-red-800' : ($ticket->status === 'answered' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-700') }}">{{ $ticket->status }}</span></td>
                        <td class="px-4 py-2 text-slate-600">{{ $ticket->created_at->format('d M Y, h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">No tickets yet. Open one if you need help.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tickets->links() }}</div>
</div>
@endsection

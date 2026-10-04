@extends('layouts.app')

@section('title', 'Support Tickets — Admin')

@section('content')
@include('admin._nav')
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-2">Support Tickets</h1>
    <p class="text-sm text-slate-600 mb-6">{{ $openCount }} open ticket(s).</p>

    <form method="GET" class="flex gap-2 mb-4">
        <select name="status" class="border rounded px-3 py-2 text-sm">
            <option value="">All</option>
            @foreach (['open', 'answered', 'closed'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button class="bg-indigo-700 text-white px-4 py-2 rounded text-sm">Filter</button>
    </form>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="text-left px-4 py-2">ID</th>
                    <th class="text-left px-4 py-2">User</th>
                    <th class="text-left px-4 py-2">Subject</th>
                    <th class="text-left px-4 py-2">Status</th>
                    <th class="text-left px-4 py-2">Opened</th>
                    <th class="text-left px-4 py-2">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ $ticket->id }}</td>
                        <td class="px-4 py-2">{{ $ticket->user?->username }}</td>
                        <td class="px-4 py-2">{{ \Illuminate\Support\Str::limit($ticket->subject, 50) }}</td>
                        <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold {{ $ticket->status === 'open' ? 'bg-red-100 text-red-800' : ($ticket->status === 'answered' ? 'bg-blue-100 text-blue-800' : 'bg-slate-200 text-slate-700') }}">{{ $ticket->status }}</span></td>
                        <td class="px-4 py-2 text-slate-600">{{ $ticket->created_at->format('d M, h:i A') }}</td>
                        <td class="px-4 py-2"><a href="{{ route('hmkr.support.show', $ticket) }}" class="text-indigo-700 hover:underline">Open →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No tickets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tickets->links() }}</div>
</div>
@endsection

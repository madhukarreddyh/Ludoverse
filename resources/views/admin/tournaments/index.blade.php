@extends('layouts.app')

@section('title', 'Tournaments — Admin')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Tournaments</h1>
        <a href="{{ route('hmkr.tournaments.create') }}"
           class="bg-indigo-700 text-white px-4 py-2 rounded hover:bg-indigo-800">New tournament</a>
    </div>

    @if (session('status'))
        <p class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">{{ session('status') }}</p>
    @endif

    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b">
                <th class="px-4 py-2">Name</th><th class="px-4 py-2">Mode</th>
                <th class="px-4 py-2">Entry</th><th class="px-4 py-2">Players</th>
                <th class="px-4 py-2">Status</th><th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tournaments as $t)
                <tr class="border-b last:border-0">
                    <td class="px-4 py-2 font-semibold">{{ $t->name }}</td>
                    <td class="px-4 py-2">{{ $t->mode }}</td>
                    <td class="px-4 py-2">₹{{ number_format($t->entry_fee_paise / 100, 2) }}</td>
                    <td class="px-4 py-2">{{ $t->participants()->count() }}/{{ $t->max_participants }}</td>
                    <td class="px-4 py-2">{{ $t->status }}</td>
                    <td class="px-4 py-2">
                        <a href="{{ route('hmkr.tournaments.show', $t) }}" class="text-indigo-700 hover:underline">Manage</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="mt-4">{{ $tournaments->links() }}</div>
</div>
@endsection

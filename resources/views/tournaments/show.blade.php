@extends('layouts.app')

@section('title', $tournament->name.' — LudoVerse')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold">{{ $tournament->name }}</h1>
    <p class="text-slate-500 mb-6">
        {{ $tournament->mode }} &middot; Entry ₹{{ number_format($tournament->entry_fee_paise / 100, 2) }} &middot;
        Status: <strong>{{ $tournament->status }}</strong>
    </p>

    @if (session('status'))
        <p class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @auth
        @if ($tournament->status === 'upcoming' && ! $registered)
            <form method="POST" action="{{ route('tournaments.join', $tournament) }}" class="mb-8">
                @csrf
                <button class="bg-indigo-700 text-white px-5 py-2 rounded hover:bg-indigo-800">
                    Register (₹{{ number_format($tournament->entry_fee_paise / 100, 2) }})
                </button>
            </form>
        @elseif ($registered)
            <p class="text-green-700 font-semibold mb-8">You are registered for this tournament.</p>
        @endif
    @endauth

    <h2 class="text-xl font-bold mb-3">Points Table</h2>
    <table class="w-full bg-white rounded-lg shadow text-sm mb-8">
        <thead>
            <tr class="text-left text-slate-500 border-b">
                <th class="px-4 py-2">#</th><th class="px-4 py-2">Player</th>
                <th class="px-4 py-2">Pts</th><th class="px-4 py-2">W</th><th class="px-4 py-2">L</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($standings as $i => $p)
                <tr class="border-b last:border-0 {{ $p->status === 'eliminated' ? 'text-slate-400' : '' }}">
                    <td class="px-4 py-2">{{ $i + 1 }}</td>
                    <td class="px-4 py-2">{{ $p->user->name ?? 'Player' }}</td>
                    <td class="px-4 py-2 font-bold">{{ $p->points }}</td>
                    <td class="px-4 py-2">{{ $p->wins }}</td>
                    <td class="px-4 py-2">{{ $p->losses }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 class="text-xl font-bold mb-3">Fixtures</h2>
    <div class="space-y-2">
        @foreach ($fixtures as $f)
            <div class="bg-white p-3 rounded-lg shadow text-sm flex items-center justify-between">
                <div>
                    <span class="font-semibold uppercase text-xs text-indigo-700">{{ $f->stage }}</span>
                    <span class="ml-2 text-slate-500">{{ $f->status }}</span>
                    @if ($f->winner_participant_id)
                        <span class="ml-2 text-green-700">Winner: {{ $f->winnerParticipant->user->name ?? '' }}</span>
                    @endif
                </div>
                @auth
                    @if ($f->status === 'pending')
                        <form method="POST" action="{{ route('tournaments.fixtures.join', $f) }}">
                            @csrf
                            @if ($tournament->mode === '4v4')
                                <select name="side" class="border rounded px-2 py-1 text-sm">
                                    <option value="1">Side 1</option>
                                    <option value="2">Side 2</option>
                                </select>
                            @endif
                            <button class="bg-indigo-700 text-white px-3 py-1 rounded text-sm">Join match</button>
                        </form>
                    @endif
                @endauth
            </div>
        @endforeach
    </div>
</div>
@endsection

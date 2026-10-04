@extends('layouts.app')

@section('title', $tournament->name.' — Admin')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-12">
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

    <div class="flex flex-wrap gap-2 mb-8">
        @if ($tournament->status === 'upcoming')
            <form method="POST" action="{{ route('hmkr.tournaments.start-league', $tournament) }}">
                @csrf
                <button class="bg-green-700 text-white px-4 py-2 rounded hover:bg-green-800">Start league</button>
            </form>
            <form method="POST" action="{{ route('hmkr.tournaments.destroy', $tournament) }}"
                  onsubmit="return confirm('Delete this tournament?')">
                @csrf @method('DELETE')
                <button class="bg-red-700 text-white px-4 py-2 rounded hover:bg-red-800">Delete</button>
            </form>
        @endif
        @if (in_array($tournament->status, ['league', 'qualifier']))
            <form method="POST" action="{{ route('hmkr.tournaments.advance', $tournament) }}">
                @csrf
                <button class="bg-indigo-700 text-white px-4 py-2 rounded hover:bg-indigo-800">Generate next stage</button>
            </form>
        @endif
        @if ($tournament->status === 'final')
            <form method="POST" action="{{ route('hmkr.tournaments.complete', $tournament) }}"
                  onsubmit="return confirm('Complete the tournament and pay prizes?')">
                @csrf
                <button class="bg-green-700 text-white px-4 py-2 rounded hover:bg-green-800">Complete &amp; pay prizes</button>
            </form>
        @endif
    </div>

    <h2 class="text-xl font-bold mb-3">Points Table</h2>
    <table class="w-full bg-white rounded-lg shadow text-sm mb-8">
        <thead>
            <tr class="text-left text-slate-500 border-b">
                <th class="px-4 py-2">#</th><th class="px-4 py-2">Player</th>
                <th class="px-4 py-2">Pts</th><th class="px-4 py-2">W</th><th class="px-4 py-2">L</th><th class="px-4 py-2">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($standings as $i => $p)
                <tr class="border-b last:border-0">
                    <td class="px-4 py-2">{{ $i + 1 }}</td>
                    <td class="px-4 py-2">{{ $p->user->name ?? '?' }}</td>
                    <td class="px-4 py-2 font-bold">{{ $p->points }}</td>
                    <td class="px-4 py-2">{{ $p->wins }}</td>
                    <td class="px-4 py-2">{{ $p->losses }}</td>
                    <td class="px-4 py-2">{{ $p->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 class="text-xl font-bold mb-3">Fixtures ({{ $fixtures->count() }})</h2>
    <table class="w-full bg-white rounded-lg shadow text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b">
                <th class="px-4 py-2">Stage</th><th class="px-4 py-2">Sides</th>
                <th class="px-4 py-2">Match</th><th class="px-4 py-2">Deadline</th>
                <th class="px-4 py-2">Status</th><th class="px-4 py-2">Winner</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($fixtures as $f)
                <tr class="border-b last:border-0">
                    <td class="px-4 py-2">{{ $f->stage }}</td>
                    <td class="px-4 py-2">
                        @if ($f->participant1_id)
                            #{{ $f->participant1_id }} vs #{{ $f->participant2_id }}
                        @else
                            Side1 ({{ count($f->side1_user_ids ?? []) }}) vs Side2 ({{ count($f->side2_user_ids ?? []) }})
                        @endif
                    </td>
                    <td class="px-4 py-2">{{ $f->match_id ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $f->join_deadline_at?->format('d M H:i') ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $f->status }}</td>
                    <td class="px-4 py-2">
                        {{ $f->winner_participant_id ? '#'.$f->winner_participant_id : ($f->winner_side ? 'Side '.$f->winner_side : '—') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

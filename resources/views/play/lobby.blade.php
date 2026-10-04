@extends('layouts.app')

@section('title', 'Play — LudoVerse')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    {{-- Header row: title + wallet chip + online count --}}
    <div class="flex items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold text-amber-400">&#127922; Game Lobby</h1>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 bg-slate-900 border border-slate-700 rounded-full px-3 py-2 text-sm font-bold text-amber-300">
                <span aria-hidden="true">&#128994;</span> {{ $onlineCount }} online
            </span>
            <a href="{{ route('wallet.index') }}" class="inline-flex items-center gap-1.5 bg-amber-500/15 border border-amber-500/40 rounded-full px-3 py-2 text-sm font-bold text-amber-300">
                <span aria-hidden="true">&#128176;</span> &#8377;{{ number_format($balancePaise / 100, 2) }}
            </a>
        </div>
    </div>

    {{-- Active match resume card --}}
    @if ($activeMatch)
        <a href="{{ route('play.match.board', $activeMatch) }}"
           class="card-dark block p-4 mb-6 border-amber-500/50 hover:border-amber-400 transition">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-amber-400 font-extrabold text-lg">&#9654; Resume your match</p>
                    <p class="text-slate-300 text-sm mt-1">
                        {{ $activeMatch->mode }} &middot; &#8377;{{ number_format($activeMatch->bet_paise / 100, 2) }} table
                        &middot; {{ ucfirst($activeMatch->status) }}
                    </p>
                </div>
                <span class="btn-chunky btn-gold !min-h-[48px]">Resume</span>
            </div>
        </a>
    @endif

    {{-- Mode cards with open bet tables --}}
    <p class="text-slate-400 text-sm mb-3">
        Open tables right now (liquidity ladder, {{ $onlineCount }} players online):
    </p>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($modes as $mode)
            <div class="card-dark p-5">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-xl font-extrabold text-slate-100">{{ $mode }}</h2>
                    <span class="text-xs text-slate-400">{{ \App\Models\LudoMatch::seatsForMode($mode) }} seats &middot; ~{{ \App\Models\LudoMatch::durationMinutesForMode($mode) }} min</span>
                </div>
                <p class="text-slate-400 text-sm mb-4">Pick your stake:</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($bets as $bet)
                        <button type="button"
                                class="btn-chunky btn-slate find-match-btn"
                                data-mode="{{ $mode }}"
                                data-bet="{{ $bet }}">
                            &#8377;{{ number_format($bet / 100, 2) }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Tournament link card --}}
    <a href="{{ route('tournaments.index') }}" class="card-dark block p-5 mt-6 hover:border-amber-500/60 transition">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-extrabold text-amber-400">&#127942; Tournaments</h2>
                <p class="text-slate-400 text-sm mt-1">Bigger brackets, bigger prizes. See fixtures and standings.</p>
            </div>
            <span class="text-amber-400 text-2xl" aria-hidden="true">&rarr;</span>
        </div>
    </a>

    {{-- How it works strip --}}
    <div class="grid grid-cols-3 gap-2 mt-6 text-center text-xs text-slate-400">
        <div class="card-dark p-3"><span class="text-lg" aria-hidden="true">&#127922;</span><br>Roll a 6 to leave base</div>
        <div class="card-dark p-3"><span class="text-lg" aria-hidden="true">&#9876;</span><br>Land on rivals to capture</div>
        <div class="card-dark p-3"><span class="text-lg" aria-hidden="true">&#127942;</span><br>Bring all 4 home to win</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var findUrl = @json(route('play.find'));
    var boardBase = @json(url('/play/match')) + '/';

    document.querySelectorAll('.find-match-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.disabled = true;
            fetch(findUrl, {
                method: 'POST',
                headers: Object.assign(window.lvHeaders(), { 'Content-Type': 'application/json' }),
                body: JSON.stringify({ mode: btn.dataset.mode, bet_paise: parseInt(btn.dataset.bet, 10) }),
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (res.ok && res.body.match_id) {
                    window.location.href = boardBase + res.body.match_id + '/board';
                } else {
                    var err = (res.body && res.body.error) || {};
                    window.toast(err.message || 'Could not find a match. Try again.', 'error');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                window.toast('Network error. Check your connection.', 'error');
                btn.disabled = false;
            });
        });
    });
})();
</script>
@endpush

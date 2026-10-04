@extends('layouts.app')

@section('title', 'LudoVerse — Play. Compete. Win.')

@section('content')
{{-- First-visit splash overlay: logo animation, fades into home. --}}
<div id="splash-overlay" class="fixed inset-0 z-50 bg-slate-950 flex-col items-center justify-center" style="display: none;">
    <img src="/icons/icon-512.png" alt="LudoVerse" class="splash-logo w-32 h-32 rounded-3xl shadow-2xl">
    <h1 class="mt-6 text-4xl font-extrabold tracking-wide">
        <span class="text-amber-400">Ludo</span><span class="text-slate-100">Verse</span>
    </h1>
    <p class="splash-tag mt-2 text-slate-400 font-semibold">Play. Compete. Win.</p>
</div>

<div class="max-w-6xl mx-auto px-4 py-16 text-center">
    <img src="/icons/icon-192.png" alt="LudoVerse" class="w-20 h-20 rounded-2xl mx-auto mb-6 shadow-xl">
    <h1 class="text-4xl md:text-5xl font-extrabold text-amber-400 mb-4">Welcome to LudoVerse</h1>
    <p class="text-lg text-slate-300 mb-8">The home of competitive Ludo. Create an account, verify your details, and join the game.</p>
    @guest
        <a href="{{ route('signup') }}" class="btn-chunky btn-gold text-lg px-10">Get Started</a>
        <p class="mt-4 text-sm text-slate-400">Already playing? <a href="{{ route('login') }}" class="text-amber-400 font-bold hover:underline">Login</a></p>
    @else
        <p class="text-slate-300 mb-6">You are logged in as <strong class="text-amber-400">{{ auth()->user()->username }}</strong>.</p>
        <a href="{{ route('play.lobby') }}" class="btn-chunky btn-gold text-lg px-10">&#127922; Enter the Lobby</a>
    @endguest

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-14 text-left">
        <div class="card-dark p-5">
            <p class="text-2xl mb-2" aria-hidden="true">&#9889;</p>
            <h2 class="font-extrabold text-slate-100 mb-1">Rush format</h2>
            <p class="text-sm text-slate-400">Fast 5–15 minute tables. Every game finishes — no marathons.</p>
        </div>
        <div class="card-dark p-5">
            <p class="text-2xl mb-2" aria-hidden="true">&#127942;</p>
            <h2 class="font-extrabold text-slate-100 mb-1">Real stakes</h2>
            <p class="text-sm text-slate-400">Climb the tables from &#8377;5 to &#8377;500 as liquidity grows.</p>
        </div>
        <div class="card-dark p-5">
            <p class="text-2xl mb-2" aria-hidden="true">&#128101;</p>
            <h2 class="font-extrabold text-slate-100 mb-1">Fair play</h2>
            <p class="text-sm text-slate-400">Server-side dice, anti-cheat scanning, and bot-free real tables.</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // First-visit splash: show once per tab session, then fade into home.
    try {
        if (sessionStorage.getItem('lv_splash_seen')) return;
        sessionStorage.setItem('lv_splash_seen', '1');
    } catch (e) { /* storage unavailable: show splash anyway */ }
    var overlay = document.getElementById('splash-overlay');
    if (!overlay) return;
    overlay.style.display = 'flex';
    setTimeout(function () {
        overlay.classList.add('splash-hide');
        setTimeout(function () { overlay.remove(); }, 700);
    }, 1500);
})();
</script>
@endpush
@endsection

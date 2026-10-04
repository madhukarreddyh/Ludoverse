@extends('layouts.app')

@section('title', 'Profile — LudoVerse')

@section('content')
<div class="max-w-xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-extrabold text-amber-400 mb-6">&#128100; Profile</h1>

    <div class="card-dark p-6 mb-4">
        <div class="flex items-center gap-4 mb-5">
            <span class="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-2xl font-black text-slate-950">
                {{ strtoupper(substr($user->username, 0, 1)) }}
            </span>
            <div>
                <p class="text-xl font-extrabold text-slate-100">{{ $user->username }}</p>
                <p class="text-sm text-slate-400">Game ID: <span class="text-amber-300 font-bold">{{ $user->game_id }}</span></p>
            </div>
        </div>

        <dl class="space-y-3 text-sm">
            <div class="flex justify-between border-b border-slate-700/60 pb-2">
                <dt class="text-slate-400">Name</dt>
                <dd class="text-slate-100 font-semibold">{{ $user->name }}</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/60 pb-2">
                <dt class="text-slate-400">Wallet balance</dt>
                <dd class="text-amber-300 font-extrabold">&#8377;{{ number_format($balancePaise / 100, 2) }}</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/60 pb-2">
                <dt class="text-slate-400">Matches played</dt>
                <dd class="text-slate-100 font-semibold">{{ $matchesPlayed }}</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/60 pb-2">
                <dt class="text-slate-400">Matches won</dt>
                <dd class="text-slate-100 font-semibold">{{ $matchesWon }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-400">Member since</dt>
                <dd class="text-slate-100 font-semibold">{{ $user->created_at?->format('M Y') }}</dd>
            </div>
        </dl>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <a href="{{ route('wallet.index') }}" class="btn-chunky btn-slate">&#128176; Wallet</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-chunky btn-danger w-full">Logout</button>
        </form>
    </div>
</div>
@endsection

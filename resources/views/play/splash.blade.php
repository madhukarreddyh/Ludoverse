@extends('layouts.app')

@section('title', 'LudoVerse')

@section('content')
<div class="fixed inset-0 z-50 bg-slate-950 flex flex-col items-center justify-center">
    <img src="/icons/icon-512.png" alt="LudoVerse" class="splash-logo w-32 h-32 rounded-3xl shadow-2xl">
    <h1 class="mt-6 text-4xl font-extrabold tracking-wide">
        <span class="text-amber-400">Ludo</span><span class="text-slate-100">Verse</span>
    </h1>
    <p class="splash-tag mt-2 text-slate-400 font-semibold">Play. Compete. Win.</p>
</div>
<script>
    setTimeout(function () { window.location.href = '/'; }, 2200);
</script>
@endsection

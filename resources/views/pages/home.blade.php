@extends('layouts.app')

@section('title', 'LudoVerse — Play. Compete. Win.')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-16 text-center">
    <h1 class="text-4xl md:text-5xl font-bold text-indigo-800 mb-4">Welcome to LudoVerse</h1>
    <p class="text-lg text-slate-600 mb-8">The home of competitive Ludo. Create an account, verify your details, and join the game.</p>
    @guest
        <a href="{{ route('signup') }}" class="bg-indigo-700 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-800">Get Started</a>
    @else
        <p class="text-slate-700">You are logged in as <strong>{{ auth()->user()->username }}</strong>.</p>
    @endguest
</div>
@endsection

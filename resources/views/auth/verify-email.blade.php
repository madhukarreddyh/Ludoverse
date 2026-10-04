@extends('layouts.app')

@section('title', 'Verify Your Email — LudoVerse')

@section('content')
<div class="max-w-md mx-auto px-4 py-12 text-center">
    <h1 class="text-2xl font-bold mb-4">Verify your email address</h1>
    <p class="text-slate-600 mb-6">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Click it to activate your account.</p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="bg-indigo-700 text-white px-5 py-2 rounded hover:bg-indigo-800">Resend verification email</button>
    </form>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Sign Up — LudoVerse')

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Create your account</h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-2 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('signup') }}" class="space-y-4 bg-white p-6 rounded-lg shadow">
        @csrf
        @include('auth._device_fingerprint')
        <div>
            <label for="name" class="block text-sm font-medium mb-1">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="username" class="block text-sm font-medium mb-1">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="email" class="block text-sm font-medium mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="mobile" class="block text-sm font-medium mb-1">Mobile (optional)</label>
            <input id="mobile" type="text" name="mobile" value="{{ old('mobile') }}" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium mb-1">Password (min 8 chars, letters + numbers)</label>
            <input id="password" type="password" name="password" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="referral_code" class="block text-sm font-medium mb-1">Referral code (optional)</label>
            <input id="referral_code" type="text" name="referral_code" value="{{ old('referral_code') }}" class="w-full border rounded px-3 py-2">
        </div>

        <div class="space-y-2 text-sm">
            <label class="flex items-start gap-2">
                <input type="checkbox" name="terms" value="1" class="mt-1" {{ old('terms') ? 'checked' : '' }}>
                <span>I accept the <a href="{{ route('terms') }}" class="text-indigo-700 underline">Terms and Conditions</a></span>
            </label>
            <label class="flex items-start gap-2">
                <input type="checkbox" name="privacy" value="1" class="mt-1" {{ old('privacy') ? 'checked' : '' }}>
                <span>I accept the <a href="{{ route('privacy') }}" class="text-indigo-700 underline">Privacy Policy</a></span>
            </label>
            <label class="flex items-start gap-2">
                <input type="checkbox" name="refund" value="1" class="mt-1" {{ old('refund') ? 'checked' : '' }}>
                <span>I accept the <a href="{{ route('refund') }}" class="text-indigo-700 underline">Refund Policy</a></span>
            </label>
        </div>

        <button type="submit" class="w-full bg-indigo-700 text-white py-2 rounded hover:bg-indigo-800">Sign Up</button>
    </form>

    <p class="mt-4 text-sm text-center">Already have an account? <a href="{{ route('login') }}" class="text-indigo-700 underline">Log in</a></p>
</div>
@endsection

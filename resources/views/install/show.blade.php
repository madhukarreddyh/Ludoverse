@extends('layouts.app')

@section('title', 'Install — LudoVerse')

@section('content')
<div class="max-w-md mx-auto px-4 py-16">
    <h1 class="text-3xl font-bold mb-2">Install LudoVerse</h1>
    <p class="text-slate-600 mb-6">Enter your license key and the email address it was issued to.</p>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-2 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('install.store') }}" class="space-y-4 bg-white p-6 rounded-lg shadow">
        @csrf
        <div>
            <label for="license_key" class="block text-sm font-medium mb-1">License key</label>
            <input id="license_key" type="text" name="license_key" value="{{ old('license_key') }}" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="email" class="block text-sm font-medium mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required class="w-full border rounded px-3 py-2">
        </div>
        <button type="submit" class="w-full bg-indigo-700 text-white py-2 rounded hover:bg-indigo-800">Activate License</button>
    </form>
</div>
@endsection

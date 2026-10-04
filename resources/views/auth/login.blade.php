@extends('layouts.app')

@section('title', 'Login — LudoVerse')

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Log in</h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-2 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4 bg-white p-6 rounded-lg shadow">
        @csrf
        <div>
            <label for="login" class="block text-sm font-medium mb-1">Email or username</label>
            <input id="login" type="text" name="login" value="{{ old('login') }}" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium mb-1">Password</label>
            <input id="password" type="password" name="password" required class="w-full border rounded px-3 py-2">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1"> Remember me
        </label>
        <button type="submit" class="w-full bg-indigo-700 text-white py-2 rounded hover:bg-indigo-800">Log In</button>
    </form>

    <p class="mt-4 text-sm text-center">No account yet? <a href="{{ route('signup') }}" class="text-indigo-700 underline">Sign up</a></p>
</div>
@endsection

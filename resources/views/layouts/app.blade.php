<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name', 'LudoVerse'))</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex flex-col bg-slate-50 text-slate-900">
    <header class="bg-indigo-700 text-white">
        <nav class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('home') }}" class="font-bold text-xl">LudoVerse</a>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('home') }}" class="hover:underline">Home</a>
                <a href="{{ route('about') }}" class="hover:underline">About Us</a>
                <a href="{{ route('contact.show') }}" class="hover:underline">Contact Us</a>
                @guest
                    <a href="{{ route('login') }}" class="hover:underline">Login</a>
                    <a href="{{ route('signup') }}" class="bg-white text-indigo-700 px-3 py-1 rounded">Sign Up</a>
                @else
                    <span class="opacity-80">{{ auth()->user()->username }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="hover:underline">Logout</button>
                    </form>
                @endguest
            </div>
        </nav>
    </header>

    <main class="flex-1">
        @if (session('status'))
            <div class="max-w-6xl mx-auto px-4 mt-4">
                <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-2 rounded">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Copyright footer: rendered only when the setting is enabled. --}}
    @if (\App\Models\Setting::bool('show_copyright'))
        <footer class="bg-slate-900 text-slate-300 mt-12">
            <div class="max-w-6xl mx-auto px-4 py-6 text-sm flex flex-col md:flex-row items-center justify-between gap-3">
                <p>{{ \App\Models\Setting::get('copyright_text') }}</p>
                <div class="flex gap-4">
                    <a href="{{ route('privacy') }}" class="hover:underline">Privacy Policy</a>
                    <a href="{{ route('refund') }}" class="hover:underline">Refund Policy</a>
                    <a href="{{ route('terms') }}" class="hover:underline">Terms &amp; Conditions</a>
                </div>
            </div>
        </footer>
    @endif
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#f59e0b">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <title>@yield('title', config('app.name', 'LudoVerse'))</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: {
                            300: '#fcd34d', 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706',
                        },
                    },
                    fontFamily: {
                        display: ['system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <style>
        /* LudoVerse gaming theme: chunky touch targets, gold-on-slate. */
        .btn-chunky {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            font-weight: 700;
            font-size: 1rem;
            line-height: 1.25;
            cursor: pointer;
            border: none;
            transition: transform 0.06s ease, filter 0.15s ease;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-chunky:active { transform: scale(0.97); }
        .btn-chunky:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }
        .btn-gold { background: linear-gradient(180deg, #fbbf24, #d97706); color: #0f172a; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35); }
        .btn-gold:hover { filter: brightness(1.08); }
        .btn-slate { background: #1e293b; color: #f1f5f9; border: 1px solid #334155; }
        .btn-slate:hover { background: #273449; }
        .btn-danger { background: #7f1d1d; color: #fecaca; border: 1px solid #991b1b; }

        .card-dark {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 1rem;
        }

        /* Toast stack. */
        #toast-stack {
            position: fixed;
            top: calc(0.75rem + env(safe-area-inset-top));
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            width: min(92vw, 420px);
            pointer-events: none;
        }
        .toast {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
            animation: toast-in 0.18s ease-out;
            pointer-events: auto;
        }
        .toast-error { background: #7f1d1d; color: #fecaca; border: 1px solid #b91c1c; }
        .toast-success { background: #14532d; color: #bbf7d0; border: 1px solid #15803d; }
        .toast-info { background: #1e3a8a; color: #bfdbfe; border: 1px solid #2563eb; }
        @keyframes toast-in {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .toast-out { opacity: 0; transition: opacity 0.25s ease; }

        /* Bottom nav safe-area. */
        .bottom-nav { padding-bottom: env(safe-area-inset-bottom); }

        /* Pulsing token for legal moves. */
        @keyframes token-pulse {
            0%, 100% { box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.9), 0 0 12px rgba(251, 191, 36, 0.8); }
            50% { box-shadow: 0 0 0 6px rgba(251, 191, 36, 0.35), 0 0 20px rgba(251, 191, 36, 0.9); }
        }
        .token-legal { animation: token-pulse 1s ease-in-out infinite; cursor: pointer; }

        /* Splash overlay. */
        #splash-overlay { transition: opacity 0.6s ease; }
        #splash-overlay.splash-hide { opacity: 0; pointer-events: none; }
        @keyframes splash-pop {
            0% { transform: scale(0.6) rotate(-8deg); opacity: 0; }
            60% { transform: scale(1.08) rotate(2deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }
        .splash-logo { animation: splash-pop 0.9s ease-out; }
        @keyframes splash-shimmer {
            0%, 100% { opacity: 0.65; }
            50% { opacity: 1; }
        }
        .splash-tag { animation: splash-shimmer 1.4s ease-in-out infinite; }
    </style>
    @stack('head')
</head>
<body class="min-h-screen flex flex-col bg-slate-950 text-slate-100 font-display" data-client-version="{{ \App\Models\Setting::get('client_version') }}">
    <div id="toast-stack" aria-live="polite"></div>

    <header class="bg-slate-900 border-b border-amber-500/30 sticky top-0 z-40">
        <nav class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-extrabold text-xl tracking-wide">
                <img src="/icons/icon-192.png" alt="LudoVerse" class="w-8 h-8 rounded-lg">
                <span class="text-amber-400">Ludo<span class="text-slate-100">Verse</span></span>
            </a>
            <div class="flex items-center gap-3 text-sm">
                <button id="install-app-btn" class="btn-chunky btn-gold !min-h-[40px] !py-2 !px-3 text-sm hidden">
                    &#8681; Install app
                </button>
                @guest
                    <a href="{{ route('login') }}" class="text-slate-300 hover:text-amber-400 font-semibold">Login</a>
                    <a href="{{ route('signup') }}" class="btn-chunky btn-gold !min-h-[40px] !py-2 !px-4 text-sm">Sign Up</a>
                @else
                    <a href="{{ route('wallet.index') }}" class="hidden sm:inline-flex items-center gap-1 text-amber-300 font-bold">
                        <span aria-hidden="true">&#128176;</span>
                        <span>&#8377;{{ number_format(app(\App\Services\WalletService::class)->balance(auth()->user()) / 100, 2) }}</span>
                    </a>
                    <span class="text-slate-400 hidden md:inline">{{ auth()->user()->username }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-slate-400 hover:text-amber-400 text-sm font-semibold">Logout</button>
                    </form>
                @endguest
            </div>
        </nav>
    </header>

    <main class="flex-1 w-full @auth pb-24 @endauth">
        @if (session('status'))
            <div class="max-w-6xl mx-auto px-4 mt-4">
                <div class="bg-green-900 border border-green-600 text-green-200 px-4 py-2 rounded-lg">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    {{-- Copyright footer: rendered only when the setting is enabled. --}}
    @if (\App\Models\Setting::bool('show_copyright'))
        <footer class="bg-slate-900 text-slate-400 border-t border-slate-800 @auth mb-16 @endauth">
            <div class="max-w-6xl mx-auto px-4 py-6 text-sm flex flex-col md:flex-row items-center justify-between gap-3">
                <p>{{ \App\Models\Setting::get('copyright_text') }}</p>
                <div class="flex gap-4">
                    <a href="{{ route('privacy') }}" class="hover:text-amber-400">Privacy Policy</a>
                    <a href="{{ route('refund') }}" class="hover:text-amber-400">Refund Policy</a>
                    <a href="{{ route('terms') }}" class="hover:text-amber-400">Terms &amp; Conditions</a>
                </div>
            </div>
        </footer>
    @endif

    {{-- Bottom navigation (mobile-first; authenticated users). --}}
    @auth
        <nav class="bottom-nav fixed bottom-0 inset-x-0 z-40 bg-slate-900/95 backdrop-blur border-t border-amber-500/30" aria-label="Primary">
            <div class="max-w-6xl mx-auto grid grid-cols-5 text-center">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-0.5 py-2.5 min-h-[56px] justify-center text-slate-300 hover:text-amber-400">
                    <span class="text-xl" aria-hidden="true">&#127968;</span>
                    <span class="text-[11px] font-semibold">Home</span>
                </a>
                <a href="{{ route('play.lobby') }}" class="flex flex-col items-center gap-0.5 py-2.5 min-h-[56px] justify-center text-slate-300 hover:text-amber-400">
                    <span class="text-xl" aria-hidden="true">&#127922;</span>
                    <span class="text-[11px] font-semibold">Play</span>
                </a>
                <a href="{{ route('wallet.index') }}" class="flex flex-col items-center gap-0.5 py-2.5 min-h-[56px] justify-center text-slate-300 hover:text-amber-400">
                    <span class="text-xl" aria-hidden="true">&#128176;</span>
                    <span class="text-[11px] font-semibold">Wallet</span>
                </a>
                <a href="{{ route('friends.page') }}" class="flex flex-col items-center gap-0.5 py-2.5 min-h-[56px] justify-center text-slate-300 hover:text-amber-400">
                    <span class="text-xl" aria-hidden="true">&#128101;</span>
                    <span class="text-[11px] font-semibold">Friends</span>
                </a>
                <a href="{{ route('profile') }}" class="flex flex-col items-center gap-0.5 py-2.5 min-h-[56px] justify-center text-slate-300 hover:text-amber-400">
                    <span class="text-xl" aria-hidden="true">&#128100;</span>
                    <span class="text-[11px] font-semibold">Profile</span>
                </a>
            </div>
        </nav>
    @endauth

    <script>
        // Global toast helper: window.toast(message, type='error'|'success'|'info').
        window.toast = function (message, type) {
            type = type || 'error';
            var stack = document.getElementById('toast-stack');
            var el = document.createElement('div');
            el.className = 'toast toast-' + type;
            el.textContent = message;
            stack.appendChild(el);
            setTimeout(function () {
                el.classList.add('toast-out');
                setTimeout(function () { el.remove(); }, 300);
            }, 3200);
        };

        // CSRF + client-version headers for fetch calls.
        window.lvHeaders = function () {
            var h = { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' };
            var cv = document.body.getAttribute('data-client-version');
            if (cv) h['X-Client-Version'] = cv;
            return h;
        };

        // Service worker registration.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () { /* offline-capable anyway */ });
            });
        }

        // PWA install prompt.
        (function () {
            var deferred = null;
            var btn = document.getElementById('install-app-btn');
            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                deferred = e;
                if (btn) btn.classList.remove('hidden');
            });
            if (btn) {
                btn.addEventListener('click', function () {
                    if (!deferred) return;
                    deferred.prompt();
                    deferred.userChoice.finally(function () {
                        deferred = null;
                        btn.classList.add('hidden');
                    });
                });
            }
            window.addEventListener('appinstalled', function () {
                if (btn) btn.classList.add('hidden');
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>

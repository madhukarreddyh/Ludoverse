<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LudoVerse live board — Match #{{ $match->id }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-white min-h-screen flex flex-col items-center justify-center p-4">
    {{-- Partner embed: read-only spectator view. No game logic lives here —
         the board below is the server-broadcast state only. --}}
    <div class="w-full max-w-md">
        <div class="flex items-center justify-between mb-4">
            <p class="font-bold">LudoVerse <span class="font-normal text-slate-400">· Match #{{ $match->id }}</span></p>
            <span class="text-xs px-2 py-1 rounded bg-slate-700">{{ $state['status'] }}</span>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            @foreach ($state['players'] as $player)
                @php $scoreKey = $player['is_bot'] ? 'bot_'.$player['id'] : (string) $player['user_id']; @endphp
                <div class="bg-slate-800 rounded p-3">
                    <p class="text-sm font-semibold capitalize" style="color: {{ $player['color'] }}">{{ $player['color'] }}</p>
                    <p class="text-2xl font-bold">{{ $state['scores'][$scoreKey] ?? 0 }} <span class="text-xs font-normal text-slate-400">pts</span></p>
                    <p class="text-xs text-slate-400">{{ $player['is_bot'] ? 'Bot' : 'Player' }} · {{ $player['status'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-slate-800 rounded p-3 text-sm">
            <p>Mode <strong>{{ $state['mode'] }}</strong> · Bet <strong>₹{{ number_format($state['bet_paise'] / 100, 2) }}</strong></p>
            @if ($state['winner_user_id'])
                <p class="text-green-400 font-semibold mt-1">Winner decided — team {{ $state['winning_team'] }}</p>
            @elseif ($state['ends_at'])
                <p class="text-slate-400 mt-1">Ends {{ $state['ends_at'] }}</p>
            @endif
        </div>

        <p class="text-center text-xs text-slate-500 mt-4">Embedded via {{ $keyName }} · Powered by LudoVerse</p>
    </div>
</body>
</html>

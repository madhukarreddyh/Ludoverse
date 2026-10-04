@extends('layouts.app')

@section('title', 'Admin Dashboard — LudoVerse')

@section('content')
@include('admin._nav')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Platform Health</h1>
        <form method="POST" action="{{ route('hmkr.maintenance') }}" class="flex items-center gap-2">
            @csrf
            <input type="hidden" name="enabled" value="{{ $maintenanceMode ? '0' : '1' }}">
            <span class="text-sm {{ $maintenanceMode ? 'text-red-700 font-bold' : 'text-slate-500' }}">
                {{ $maintenanceMode ? 'MAINTENANCE ON' : 'Live' }}
            </span>
            <button type="submit" class="px-3 py-1 rounded text-sm {{ $maintenanceMode ? 'bg-green-600 text-white' : 'bg-red-600 text-white' }}">
                {{ $maintenanceMode ? 'Disable maintenance' : 'Enable maintenance' }}
            </button>
        </form>
    </div>

    @if ($unreadNotices > 0)
        <div class="mb-6 bg-amber-50 border border-amber-300 rounded-lg p-4">
            <p class="font-semibold text-amber-900 mb-2">{{ $unreadNotices }} unread admin notice(s)</p>
            @foreach ($notices->where('is_read', false) as $notice)
                <div class="flex items-start justify-between gap-4 py-1 border-t border-amber-200 first:border-t-0">
                    <div>
                        <p class="font-semibold text-sm">{{ $notice->title }}</p>
                        <p class="text-sm text-amber-900">{{ $notice->body }}</p>
                        <p class="text-xs text-slate-500">{{ $notice->created_at->format('d M Y, h:i A') }}</p>
                    </div>
                    <form method="POST" action="{{ route('hmkr.notices.read', $notice) }}">
                        @csrf
                        <button class="text-xs text-indigo-700 hover:underline">Mark read</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Online players</p><p class="text-2xl font-bold">{{ $onlinePlayers }}</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Running matches</p><p class="text-2xl font-bold">{{ $runningMatches }}</p><p class="text-xs text-slate-400">{{ $waitingMatches }} waiting</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Deposits today</p><p class="text-2xl font-bold text-green-700">₹{{ number_format($depositsToday / 100, 2) }}</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Withdrawals today</p><p class="text-2xl font-bold text-red-700">₹{{ number_format($withdrawalsToday / 100, 2) }}</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Platform profit today</p><p class="text-2xl font-bold {{ $profitToday >= 0 ? 'text-green-700' : 'text-red-700' }}">₹{{ number_format($profitToday / 100, 2) }}</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Bot matches (last 100)</p><p class="text-2xl font-bold">{{ $botMatchPct }}%</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Open fraud alerts</p><p class="text-2xl font-bold {{ $openFraudAlerts > 0 ? 'text-red-700' : '' }}">{{ $openFraudAlerts }}</p><a href="{{ route('hmkr.fraud.index') }}" class="text-xs text-indigo-700 hover:underline">Triage →</a></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Bot difficulty</p><p class="text-2xl font-bold capitalize">{{ $botDifficulty }}</p><p class="text-xs text-slate-400">client v{{ $clientVersion }}</p></div>
    </div>

    <h2 class="text-xl font-bold mb-3">Recent cheat flags</h2>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="text-left px-4 py-2">Time</th>
                    <th class="text-left px-4 py-2">User</th>
                    <th class="text-left px-4 py-2">Type</th>
                    <th class="text-left px-4 py-2">Match</th>
                    <th class="text-left px-4 py-2">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentCheatLogs as $log)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2 text-slate-600">{{ $log->created_at->format('d M, h:i A') }}</td>
                        <td class="px-4 py-2"><a href="{{ route('hmkr.users.show', $log->user) }}" class="text-indigo-700 hover:underline">{{ $log->user?->username ?? '—' }}</a></td>
                        <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">{{ $log->type }}</span></td>
                        <td class="px-4 py-2">{{ $log->match_id ?? '—' }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-600">{{ json_encode($log->details) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No cheat flags recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

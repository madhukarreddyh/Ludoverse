@extends('layouts.app')

@section('title', 'User '.$user->username.' — Admin')

@section('content')
@include('admin._nav')
<div class="max-w-6xl mx-auto px-4 py-8">
    <a href="{{ route('hmkr.users.index') }}" class="text-sm text-indigo-700 hover:underline">← Back to users</a>
    <div class="flex items-center justify-between mt-2 mb-6">
        <h1 class="text-3xl font-bold">{{ $user->username }}
            <span class="text-base font-normal text-slate-500">{{ $user->email }} · {{ $user->game_id }}</span>
        </h1>
        <div class="flex gap-2">
            @if ($user->status === 'active')
                <form method="POST" action="{{ route('hmkr.users.suspend', $user) }}">@csrf<button class="bg-red-600 text-white px-3 py-1 rounded text-sm">Suspend</button></form>
                <form method="POST" action="{{ route('hmkr.users.freeze', $user) }}">@csrf<button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Freeze wallet</button></form>
            @else
                @if ($user->status === 'suspended')
                    <form method="POST" action="{{ route('hmkr.users.unsuspend', $user) }}">@csrf<button class="bg-green-600 text-white px-3 py-1 rounded text-sm">Unsuspend</button></form>
                @endif
                @if ($user->status === 'frozen')
                    <form method="POST" action="{{ route('hmkr.users.unfreeze', $user) }}">@csrf<button class="bg-green-600 text-white px-3 py-1 rounded text-sm">Unfreeze wallet</button></form>
                @endif
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Ledger balance</p><p class="text-xl font-bold">₹{{ number_format($balance / 100, 2) }}</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Spendable (available)</p><p class="text-xl font-bold text-green-700">₹{{ number_format($available / 100, 2) }}</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Locked bonus</p><p class="text-xl font-bold text-amber-700">₹{{ number_format($locked / 100, 2) }}</p></div>
        <div class="bg-white rounded-lg shadow p-4"><p class="text-xs text-slate-500">Lifetime wagered</p><p class="text-xl font-bold">₹{{ number_format($user->wagered_paise / 100, 2) }}</p></div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <div>
            <h2 class="text-xl font-bold mb-3">Devices</h2>
            <div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
                <table class="w-full text-sm">
                    <tbody>
                        @forelse ($devices as $device)
                            <tr class="border-t border-slate-100 first:border-t-0">
                                <td class="px-4 py-2 font-mono text-xs">{{ substr($device->device_hash, 0, 16) }}…</td>
                                <td class="px-4 py-2 text-slate-600 text-xs">{{ $device->ip_address }}<br>{{ \Illuminate\Support\Str::limit($device->user_agent, 40) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <form method="POST" action="{{ route('hmkr.devices.destroy', $device) }}">@csrf @method('DELETE')<button class="text-xs text-red-700 hover:underline">Unlink</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-4 text-slate-500 text-sm">No devices linked yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h2 class="text-xl font-bold mb-3">Bonus locks</h2>
            <div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
                <table class="w-full text-sm">
                    <tbody>
                        @forelse ($locks as $lock)
                            <tr class="border-t border-slate-100 first:border-t-0">
                                <td class="px-4 py-2">Requires wager ₹{{ number_format($lock->required_wager_paise / 100, 2) }}</td>
                                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold {{ $lock->released ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $lock->released ? 'Released' : 'Locked' }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-4 text-slate-500 text-sm">No bonus locks.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h2 class="text-xl font-bold mb-3">Cheat flags</h2>
            <div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
                <table class="w-full text-sm">
                    <tbody>
                        @forelse ($cheatLogs as $log)
                            <tr class="border-t border-slate-100 first:border-t-0">
                                <td class="px-4 py-2 text-slate-600">{{ $log->created_at->format('d M, h:i') }}</td>
                                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">{{ $log->type }}</span></td>
                                <td class="px-4 py-2 font-mono text-xs">{{ json_encode($log->details) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-4 text-slate-500 text-sm">No cheat flags.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="text-xl font-bold mb-3">Recent matches</h2>
            <div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
                <table class="w-full text-sm">
                    <tbody>
                        @forelse ($matches as $m)
                            <tr class="border-t border-slate-100 first:border-t-0">
                                <td class="px-4 py-2">#{{ $m->id }}</td>
                                <td class="px-4 py-2">{{ $m->mode }} · ₹{{ number_format($m->bet_paise / 100, 2) }}</td>
                                <td class="px-4 py-2">{{ $m->status }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $m->created_at->format('d M, h:i') }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-4 text-slate-500 text-sm">No matches yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h2 class="text-xl font-bold mb-3">Fraud flags</h2>
            <div class="bg-white rounded-lg shadow overflow-x-auto mb-6">
                <table class="w-full text-sm">
                    <tbody>
                        @forelse ($fraudFlags as $flag)
                            <tr class="border-t border-slate-100 first:border-t-0">
                                <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">{{ $flag->type }}</span></td>
                                <td class="px-4 py-2">{{ $flag->status }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $flag->created_at->format('d M, h:i') }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-4 text-slate-500 text-sm">No fraud flags.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h2 class="text-xl font-bold mb-3">Ledger (latest)</h2>
            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        @forelse ($ledger as $entry)
                            <tr class="border-t border-slate-100 first:border-t-0">
                                <td class="px-4 py-2 text-slate-600">{{ $entry->created_at->format('d M, h:i') }}</td>
                                <td class="px-4 py-2">{{ $entry->transaction_type }}</td>
                                <td class="px-4 py-2 text-right font-semibold {{ $entry->amount_paise >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $entry->amount_paise >= 0 ? '+' : '' }}₹{{ number_format($entry->amount_paise / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-4 text-slate-500 text-sm">No ledger entries.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

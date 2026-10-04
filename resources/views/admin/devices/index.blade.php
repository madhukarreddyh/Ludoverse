@extends('layouts.app')

@section('title', 'Devices — Admin')

@section('content')
@include('admin._nav')
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Devices</h1>
    <p class="text-sm text-slate-600 mb-4">One account per device. Banning a hash blocks all signup/login attempts from it.</p>

    <form method="GET" class="flex gap-2 mb-4">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search hash or username…" class="border rounded px-3 py-2 text-sm w-72">
        <button class="bg-indigo-700 text-white px-4 py-2 rounded text-sm">Search</button>
    </form>

    <div class="bg-white rounded-lg shadow overflow-x-auto mb-10">
        <table class="w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="text-left px-4 py-2">User</th>
                    <th class="text-left px-4 py-2">Device hash</th>
                    <th class="text-left px-4 py-2">IP</th>
                    <th class="text-left px-4 py-2">Linked</th>
                    <th class="text-left px-4 py-2">Status</th>
                    <th class="text-left px-4 py-2">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($devices as $device)
                    @php $isBanned = isset($banned[$device->device_hash]); @endphp
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2"><a href="{{ route('hmkr.users.show', $device->user) }}" class="text-indigo-700 hover:underline">{{ $device->user?->username ?? '—' }}</a></td>
                        <td class="px-4 py-2 font-mono text-xs">{{ substr($device->device_hash, 0, 20) }}…</td>
                        <td class="px-4 py-2 text-slate-600">{{ $device->ip_address ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $device->created_at?->format('d M Y, h:i A') ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($isBanned)
                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">Banned</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800">OK</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @unless ($isBanned)
                                <form method="POST" action="{{ route('hmkr.devices.ban') }}">
                                    @csrf
                                    <input type="hidden" name="device_hash" value="{{ $device->device_hash }}">
                                    <button class="text-xs text-red-700 hover:underline">Ban device</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mb-4">{{ $devices->links() }}</div>

    <h2 class="text-xl font-bold mb-3">Banned devices</h2>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="text-left px-4 py-2">Hash</th>
                    <th class="text-left px-4 py-2">Reason</th>
                    <th class="text-left px-4 py-2">Banned at</th>
                    <th class="text-left px-4 py-2">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bannedList as $bannedDevice)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2 font-mono text-xs">{{ substr($bannedDevice->device_hash, 0, 20) }}…</td>
                        <td class="px-4 py-2 text-slate-600">{{ $bannedDevice->reason }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $bannedDevice->created_at->format('d M Y, h:i A') }}</td>
                        <td class="px-4 py-2">
                            <form method="POST" action="{{ route('hmkr.devices.unban', $bannedDevice) }}">
                                @csrf @method('DELETE')
                                <button class="text-xs text-green-700 hover:underline">Unban</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No banned devices.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $bannedList->links() }}</div>
</div>
@endsection

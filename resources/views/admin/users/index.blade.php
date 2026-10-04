@extends('layouts.app')

@section('title', 'Users — Admin')

@section('content')
@include('admin._nav')
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Users</h1>

    <form method="GET" class="flex gap-2 mb-4">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search username, email, game_id, mobile…" class="border rounded px-3 py-2 text-sm w-72">
        <select name="status" class="border rounded px-3 py-2 text-sm">
            <option value="">All statuses</option>
            @foreach (['active', 'suspended', 'frozen'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button class="bg-indigo-700 text-white px-4 py-2 rounded text-sm">Search</button>
    </form>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="text-left px-4 py-2">ID</th>
                    <th class="text-left px-4 py-2">Username</th>
                    <th class="text-left px-4 py-2">Email</th>
                    <th class="text-left px-4 py-2">Game ID</th>
                    <th class="text-left px-4 py-2">Status</th>
                    <th class="text-left px-4 py-2">Wagered</th>
                    <th class="text-left px-4 py-2">Last seen</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ $user->id }}</td>
                        <td class="px-4 py-2"><a href="{{ route('hmkr.users.show', $user) }}" class="text-indigo-700 hover:underline font-semibold">{{ $user->username }}</a></td>
                        <td class="px-4 py-2 text-slate-600">{{ $user->email }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $user->game_id ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $user->status === 'active' ? 'bg-green-100 text-green-800' : ($user->status === 'frozen' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">{{ $user->status }}</span>
                        </td>
                        <td class="px-4 py-2">₹{{ number_format($user->wagered_paise / 100, 2) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $user->last_seen_at?->diffForHumans() ?? 'never' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</div>
@endsection

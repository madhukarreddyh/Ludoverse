@extends('layouts.app')

@section('title', 'API Keys — Admin')

@section('content')
@include('admin._nav')
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">API Keys</h1>

    @if ($plainKey)
        <div class="mb-6 bg-red-50 border border-red-300 rounded-lg p-4">
            <p class="font-bold text-red-900">Copy this key NOW — it will never be shown again:</p>
            <code class="block mt-2 bg-white border rounded p-3 font-mono text-sm break-all select-all">{{ $plainKey }}</code>
            <p class="text-xs text-slate-600 mt-2">Key name: {{ $plainKeyName }}</p>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="font-bold mb-3">Generate new key</h2>
        <form method="POST" action="{{ route('hmkr.api-keys.store') }}" class="grid md:grid-cols-4 gap-3 items-end">
            @csrf
            <div>
                <label class="text-xs text-slate-500">Name</label>
                <input type="text" name="name" required maxlength="100" placeholder="e.g. Partner XYZ" class="border rounded px-3 py-2 text-sm w-full">
            </div>
            <div>
                <label class="text-xs text-slate-500">Type</label>
                <select name="type" class="border rounded px-3 py-2 text-sm w-full">
                    <option value="public">Public (player API)</option>
                    <option value="private">Private (partner API — powerful)</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">IP whitelist (private keys — comma separated)</label>
                <input type="text" name="ip_whitelist" placeholder="203.0.113.10, 203.0.113.11" class="border rounded px-3 py-2 text-sm w-full">
            </div>
            <button class="bg-indigo-700 text-white px-4 py-2 rounded text-sm">Generate</button>
        </form>
        @error('name')<p class="text-red-700 text-sm mt-2">{{ $message }}</p>@enderror
    </div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="text-left px-4 py-2">Name</th>
                    <th class="text-left px-4 py-2">Type</th>
                    <th class="text-left px-4 py-2">Prefix</th>
                    <th class="text-left px-4 py-2">IP whitelist</th>
                    <th class="text-left px-4 py-2">Status</th>
                    <th class="text-left px-4 py-2">Last used</th>
                    <th class="text-left px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($keys as $key)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2 font-semibold">{{ $key->name }}</td>
                        <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold {{ $key->type === 'private' ? 'bg-purple-100 text-purple-800' : 'bg-slate-200 text-slate-800' }}">{{ $key->type }}</span></td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $key->key_prefix }}…</td>
                        <td class="px-4 py-2 font-mono text-xs text-slate-600">{{ $key->ip_whitelist ? implode(', ', $key->ip_whitelist) : '—' }}</td>
                        <td class="px-4 py-2">{{ $key->is_active ? 'Active' : 'Disabled' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $key->last_used_at?->format('d M, h:i A') ?? 'never' }}</td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-2 text-xs">
                                <a href="{{ route('hmkr.api-keys.logs', $key) }}" class="text-indigo-700 hover:underline">Logs</a>
                                @if ($key->is_active)
                                    <form method="POST" action="{{ route('hmkr.api-keys.disable', $key) }}">@csrf<button class="text-amber-700 hover:underline">Disable</button></form>
                                @else
                                    <form method="POST" action="{{ route('hmkr.api-keys.enable', $key) }}">@csrf<button class="text-green-700 hover:underline">Enable</button></form>
                                @endif
                                <form method="POST" action="{{ route('hmkr.api-keys.regenerate', $key) }}">@csrf<button class="text-blue-700 hover:underline">Regenerate</button></form>
                                <form method="POST" action="{{ route('hmkr.api-keys.destroy', $key) }}">@csrf @method('DELETE')<button class="text-red-700 hover:underline" onclick="return confirm('Delete this key?')">Delete</button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No API keys yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $keys->links() }}</div>
</div>
@endsection

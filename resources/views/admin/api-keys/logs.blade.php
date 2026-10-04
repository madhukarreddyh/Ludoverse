@extends('layouts.app')

@section('title', 'API Key Logs — Admin')

@section('content')
@include('admin._nav')
<div class="max-w-6xl mx-auto px-4 py-8">
    <a href="{{ route('hmkr.api-keys.index') }}" class="text-sm text-indigo-700 hover:underline">← Back to API keys</a>
    <h1 class="text-3xl font-bold mt-2 mb-6">Logs — {{ $key->name }} <span class="text-base font-normal text-slate-500">({{ $key->type }})</span></h1>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="text-left px-4 py-2">Time</th>
                    <th class="text-left px-4 py-2">Endpoint</th>
                    <th class="text-left px-4 py-2">IP</th>
                    <th class="text-left px-4 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2 text-slate-600">{{ $log->created_at->format('d M Y, h:i:s A') }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $log->endpoint }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $log->ip }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $log->status === 200 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $log->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No requests logged yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection

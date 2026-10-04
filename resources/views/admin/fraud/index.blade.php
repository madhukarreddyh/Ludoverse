@extends('layouts.app')

@section('title', 'Fraud Triage — Admin')

@section('content')
@include('admin._nav')
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-2">Fraud Triage</h1>
    <p class="text-sm text-slate-600 mb-6">{{ $openCount }} open flag(s). Confirm → freeze wallet or suspend; dismiss clears the flag.</p>

    <form method="GET" class="flex gap-2 mb-4">
        <select name="status" class="border rounded px-3 py-2 text-sm">
            @foreach (['open', 'confirmed', 'dismissed'] as $s)
                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <select name="type" class="border rounded px-3 py-2 text-sm">
            <option value="">All types</option>
            @foreach ($types as $t)
                <option value="{{ $t }}" {{ $type === $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
        <button class="bg-indigo-700 text-white px-4 py-2 rounded text-sm">Filter</button>
    </form>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="text-left px-4 py-2">ID</th>
                    <th class="text-left px-4 py-2">User</th>
                    <th class="text-left px-4 py-2">Type</th>
                    <th class="text-left px-4 py-2">Status</th>
                    <th class="text-left px-4 py-2">Raised</th>
                    <th class="text-left px-4 py-2">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($flags as $flag)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ $flag->id }}</td>
                        <td class="px-4 py-2"><a href="{{ route('hmkr.users.show', $flag->user) }}" class="text-indigo-700 hover:underline">{{ $flag->user?->username ?? '—' }}</a></td>
                        <td class="px-4 py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">{{ $flag->type }}</span></td>
                        <td class="px-4 py-2">{{ $flag->status }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $flag->created_at->format('d M, h:i A') }}</td>
                        <td class="px-4 py-2"><a href="{{ route('hmkr.fraud.show', $flag) }}" class="text-indigo-700 hover:underline text-sm">Review →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No flags in this view.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $flags->links() }}</div>
</div>
@endsection

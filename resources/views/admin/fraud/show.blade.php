@extends('layouts.app')

@section('title', 'Fraud Flag #'.$flag->id.' — Admin')

@section('content')
@include('admin._nav')
<div class="max-w-3xl mx-auto px-4 py-8">
    <a href="{{ route('hmkr.fraud.index') }}" class="text-sm text-indigo-700 hover:underline">← Back to triage</a>
    <h1 class="text-3xl font-bold mt-2 mb-6">Flag #{{ $flag->id }} — {{ $flag->type }}</h1>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <dl class="text-sm space-y-2">
            <div class="flex gap-4"><dt class="w-32 text-slate-500">User</dt><dd><a href="{{ route('hmkr.users.show', $flag->user) }}" class="text-indigo-700 hover:underline font-semibold">{{ $flag->user?->username }}</a> (status: {{ $flag->user?->status }})</dd></div>
            <div class="flex gap-4"><dt class="w-32 text-slate-500">Status</dt><dd>{{ $flag->status }}</dd></div>
            <div class="flex gap-4"><dt class="w-32 text-slate-500">Raised</dt><dd>{{ $flag->created_at->format('d M Y, h:i A') }}</dd></div>
        </dl>
        <h2 class="font-bold mt-4 mb-2">Evidence</h2>
        <pre class="bg-slate-50 border rounded p-3 text-xs font-mono overflow-x-auto">{{ json_encode($flag->details, JSON_PRETTY_PRINT) }}</pre>
    </div>

    @if ($flag->status === 'open')
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="font-bold mb-3">Confirm flag</h2>
            <form method="POST" action="{{ route('hmkr.fraud.confirm', $flag) }}" class="space-y-3">
                @csrf
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="action" value="freeze_wallet" checked> Freeze wallet (debits blocked pending review)</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="action" value="suspend_account"> Suspend account (no login, no play)</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="action" value="flag_only"> Confirm flag only (no account action)</label>
                <button class="bg-red-600 text-white px-4 py-2 rounded text-sm">Confirm</button>
            </form>
            <form method="POST" action="{{ route('hmkr.fraud.dismiss', $flag) }}" class="mt-4">
                @csrf
                <button class="text-sm text-slate-600 hover:underline">Dismiss flag (no violation)</button>
            </form>
        </div>
    @endif
</div>
@endsection

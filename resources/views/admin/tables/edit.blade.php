@extends('layouts.app')

@section('title', 'Tables — Admin')

@section('content')
@include('admin._nav')
<div class="max-w-3xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-2">Liquidity — Table Levels</h1>
    <p class="text-sm text-slate-600 mb-6">Currently <strong>{{ $online }}</strong> players online. Open bet levels: <strong>{{ implode(', ', array_map(fn ($b) => '₹'.($b / 100), $current)) }}</strong></p>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('hmkr.tables.update') }}" class="space-y-4">
            @csrf
            <label class="flex items-start gap-3">
                <input type="checkbox" name="auto_mode" value="1" {{ $auto ? 'checked' : '' }} class="mt-1">
                <span class="text-sm"><strong>Auto mode</strong> — bet levels follow the online-count ladder:<br>
                    <span class="text-slate-600">&lt;50 → ₹5, ₹10 · 50–200 → ₹5, ₹10 · &gt;200 → +₹50 · &gt;1000 → +₹100, ₹500</span>
                </span>
            </label>

            <div>
                <p class="text-sm font-semibold mb-2">Manual levels (used when auto mode is OFF)</p>
                <div class="flex flex-wrap gap-3">
                    @foreach ($levels as $level)
                        <label class="flex items-center gap-2 text-sm bg-slate-50 border rounded px-3 py-2">
                            <input type="checkbox" name="levels[]" value="{{ $level }}" {{ in_array($level, array_map('intval', $manual), true) ? 'checked' : '' }}>
                            ₹{{ number_format($level / 100, $level >= 10000 ? 0 : 2) }}
                        </label>
                    @endforeach
                </div>
            </div>

            <button class="bg-indigo-700 text-white px-4 py-2 rounded text-sm">Save table settings</button>
        </form>
    </div>
</div>
@endsection

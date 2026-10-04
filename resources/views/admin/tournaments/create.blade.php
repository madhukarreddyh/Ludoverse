@extends('layouts.app')

@section('title', 'New Tournament — Admin')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">New Tournament</h1>

    @if ($errors->any())
        <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('hmkr.tournaments.store') }}" class="space-y-4 bg-white p-6 rounded-lg shadow">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium mb-1">Name</label>
            <input id="name" type="text" name="name" required maxlength="255" value="{{ old('name') }}"
                   class="w-full border rounded px-3 py-2" placeholder="Diwali Dhamaka Cup">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="mode" class="block text-sm font-medium mb-1">Mode</label>
                <select id="mode" name="mode" class="w-full border rounded px-3 py-2">
                    <option value="1v1" {{ old('mode') === '1v1' ? 'selected' : '' }}>1v1</option>
                    <option value="4v4" {{ old('mode') === '4v4' ? 'selected' : '' }}>4v4</option>
                </select>
                <p class="text-xs text-slate-500 mt-1">4v4 needs a multiple of 8 players (min 16).</p>
            </div>
            <div>
                <label for="entry_fee_paise" class="block text-sm font-medium mb-1">Entry fee (paise)</label>
                <input id="entry_fee_paise" type="number" min="0" name="entry_fee_paise" required
                       value="{{ old('entry_fee_paise', 10000) }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label for="max_participants" class="block text-sm font-medium mb-1">Max participants</label>
                <input id="max_participants" type="number" min="4" max="256" name="max_participants" required
                       value="{{ old('max_participants', 8) }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label for="starts_at" class="block text-sm font-medium mb-1">Starts at (optional)</label>
                <input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"
                       class="w-full border rounded px-3 py-2">
            </div>
        </div>
        <button type="submit" class="bg-indigo-700 text-white px-5 py-2 rounded hover:bg-indigo-800">Create</button>
    </form>
</div>
@endsection

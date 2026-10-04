@extends('layouts.app')

@section('title', 'Tournaments — LudoVerse')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Tournaments</h1>

    @if ($tournaments->isEmpty())
        <p class="text-slate-500">No tournaments running right now. Check back soon.</p>
    @else
        <div class="space-y-3">
            @foreach ($tournaments as $t)
                <a href="{{ route('tournaments.show', $t) }}"
                   class="block bg-white p-4 rounded-lg shadow hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-bold">{{ $t->name }}</div>
                            <div class="text-sm text-slate-500">
                                {{ $t->mode }} &middot;
                                Entry ₹{{ number_format($t->entry_fee_paise / 100, 2) }} &middot;
                                {{ $t->participants()->count() }}/{{ $t->max_participants }} players
                            </div>
                        </div>
                        <span class="text-xs font-semibold uppercase px-2 py-1 rounded bg-indigo-100 text-indigo-700">
                            {{ $t->status }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $tournaments->links() }}</div>
    @endif
</div>
@endsection

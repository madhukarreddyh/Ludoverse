@extends('layouts.app')

@section('title', 'New Support Ticket — LudoVerse')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Open a support ticket</h1>
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('support.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="text-sm font-semibold">Subject</label>
                <input type="text" name="subject" required maxlength="150" value="{{ old('subject') }}" class="border rounded px-3 py-2 text-sm w-full mt-1" placeholder="e.g. My withdrawal hasn't arrived">
                @error('subject')<p class="text-red-700 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="text-sm font-semibold">Message</label>
                <textarea name="message" rows="6" required maxlength="5000" class="border rounded px-3 py-2 text-sm w-full mt-1" placeholder="Describe the issue in detail…">{{ old('message') }}</textarea>
                @error('message')<p class="text-red-700 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <button class="bg-indigo-700 text-white px-4 py-2 rounded text-sm">Submit ticket</button>
        </form>
    </div>
</div>
@endsection

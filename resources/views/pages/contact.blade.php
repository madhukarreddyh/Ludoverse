@extends('layouts.app')

@section('title', 'Contact Us — LudoVerse')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Contact Us</h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-2 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contact.store') }}" class="space-y-4 bg-white p-6 rounded-lg shadow">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium mb-1">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="email" class="block text-sm font-medium mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required
                   class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="message" class="block text-sm font-medium mb-1">Message</label>
            <textarea id="message" name="message" rows="5" required
                      class="w-full border rounded px-3 py-2">{{ old('message') }}</textarea>
        </div>
        <button type="submit" class="bg-indigo-700 text-white px-5 py-2 rounded hover:bg-indigo-800">
            Send Message
        </button>
    </form>
</div>
@endsection

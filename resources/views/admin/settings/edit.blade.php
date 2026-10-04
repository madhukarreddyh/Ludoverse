@extends('layouts.app')

@section('title', 'Settings — Admin')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Site Settings</h1>

    <form method="POST" action="{{ route('hmkr.settings.update') }}" class="space-y-4 bg-white p-6 rounded-lg shadow">
        @csrf
        <div>
            <label for="copyright_text" class="block text-sm font-medium mb-1">Copyright footer text</label>
            <textarea id="copyright_text" name="copyright_text" rows="2" required
                      class="w-full border rounded px-3 py-2">{{ old('copyright_text', $copyright_text) }}</textarea>
            @error('copyright_text')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm">
            {{-- Hidden field so an unchecked box still submits "off". --}}
            <input type="hidden" name="show_copyright" value="0">
            <input type="checkbox" name="show_copyright" value="1" {{ old('show_copyright', $show_copyright) ? 'checked' : '' }}>
            Show copyright footer
        </label>
        <button type="submit" class="bg-indigo-700 text-white px-5 py-2 rounded hover:bg-indigo-800">Save Settings</button>
    </form>
</div>
@endsection

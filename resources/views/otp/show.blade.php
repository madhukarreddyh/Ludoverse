@extends('layouts.app')

@section('title', 'Verify Mobile — LudoVerse')

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Verify your mobile number</h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-2 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('otp.send') }}" class="space-y-4 bg-white p-6 rounded-lg shadow mb-6">
        @csrf
        <div>
            <label for="mobile" class="block text-sm font-medium mb-1">Mobile number</label>
            <input id="mobile" type="text" name="mobile" value="{{ old('mobile', auth()->user()->mobile) }}" required class="w-full border rounded px-3 py-2">
        </div>
        <button type="submit" class="w-full bg-indigo-700 text-white py-2 rounded hover:bg-indigo-800">Send OTP</button>
    </form>

    <form method="POST" action="{{ route('otp.verify') }}" class="space-y-4 bg-white p-6 rounded-lg shadow">
        @csrf
        <div>
            <label for="verify_mobile" class="block text-sm font-medium mb-1">Mobile number</label>
            <input id="verify_mobile" type="text" name="mobile" value="{{ old('mobile', auth()->user()->mobile) }}" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label for="code" class="block text-sm font-medium mb-1">6-digit code</label>
            <input id="code" type="text" name="code" inputmode="numeric" maxlength="6" required class="w-full border rounded px-3 py-2">
        </div>
        <button type="submit" class="w-full bg-emerald-700 text-white py-2 rounded hover:bg-emerald-800">Verify Code</button>
    </form>
</div>
@endsection

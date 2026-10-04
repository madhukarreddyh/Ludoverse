@extends('layouts.app')

@section('title', 'Licenses — Admin')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">License Management</h1>

    <form method="POST" action="{{ route('hmkr.licenses.store') }}" class="bg-white p-6 rounded-lg shadow mb-8 flex flex-col md:flex-row gap-3 md:items-end">
        @csrf
        <div class="flex-1">
            <label for="email" class="block text-sm font-medium mb-1">Issue a new license to</label>
            <input id="email" type="email" name="email" required placeholder="customer@example.com" class="w-full border rounded px-3 py-2">
            @error('email')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="bg-indigo-700 text-white px-5 py-2 rounded hover:bg-indigo-800">Generate License</button>
    </form>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="text-left px-4 py-2">License Key</th>
                    <th class="text-left px-4 py-2">Email</th>
                    <th class="text-left px-4 py-2">Status</th>
                    <th class="text-left px-4 py-2">Created</th>
                    <th class="text-left px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($licenses as $license)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-mono">{{ $license->license_key }}</td>
                        <td class="px-4 py-2">{{ $license->email }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs {{ $license->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $license->status }}
                            </span>
                        </td>
                        <td class="px-4 py-2">{{ $license->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2">
                            <div class="flex gap-2">
                                @if ($license->status !== 'active')
                                    <form method="POST" action="{{ route('hmkr.licenses.activate', $license) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-green-700 underline">Activate</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('hmkr.licenses.disable', $license) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-amber-700 underline">Disable</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('hmkr.licenses.destroy', $license) }}"
                                      onsubmit="return confirm('Delete this license permanently?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-700 underline">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No licenses yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $licenses->links() }}</div>
</div>
@endsection

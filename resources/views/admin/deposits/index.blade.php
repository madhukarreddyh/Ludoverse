@extends('layouts.app')

@section('title', 'Manual Deposits — Admin')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Manual Deposits</h1>

    @if ($deposits->count())
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-4 py-2">Date</th>
                        <th class="text-left px-4 py-2">User</th>
                        <th class="text-right px-4 py-2">Amount</th>
                        <th class="text-left px-4 py-2">UTR</th>
                        <th class="text-left px-4 py-2">Screenshot</th>
                        <th class="text-left px-4 py-2">Status</th>
                        <th class="text-left px-4 py-2">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deposits as $deposit)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-2 text-slate-600">{{ $deposit->created_at->format('d M Y, h:i A') }}</td>
                            <td class="px-4 py-2">{{ $deposit->user?->username ?? '—' }}</td>
                            <td class="px-4 py-2 text-right font-semibold">₹{{ number_format($deposit->amount_paise / 100, 2) }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $deposit->utr }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ asset('storage/'.$deposit->screenshot_path) }}" target="_blank" class="text-indigo-700 hover:underline">View</a>
                            </td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold
                                    {{ $deposit->status === 'approved' ? 'bg-green-100 text-green-800' : ($deposit->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ ucfirst($deposit->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                @if ($deposit->status === 'pending')
                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ route('hmkr.deposits.approve', $deposit) }}">
                                            @csrf
                                            <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700">Approve &amp; credit</button>
                                        </form>
                                        <form method="POST" action="{{ route('hmkr.deposits.reject', $deposit) }}">
                                            @csrf
                                            <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-xs hover:bg-red-700">Reject</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-slate-400 text-xs">Reviewed</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $deposits->links() }}</div>
    @else
        <p class="text-slate-500">No manual deposit claims yet.</p>
    @endif
</div>
@endsection

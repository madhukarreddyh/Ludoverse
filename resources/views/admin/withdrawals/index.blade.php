@extends('layouts.app')

@section('title', 'Withdrawals — Admin')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Withdrawal Requests</h1>

    @if ($withdrawals->count())
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-4 py-2">Date</th>
                        <th class="text-left px-4 py-2">User</th>
                        <th class="text-left px-4 py-2">Method</th>
                        <th class="text-right px-4 py-2">Amount</th>
                        <th class="text-right px-4 py-2">TDS</th>
                        <th class="text-right px-4 py-2">Commission</th>
                        <th class="text-right px-4 py-2">Net payable</th>
                        <th class="text-left px-4 py-2">Details</th>
                        <th class="text-left px-4 py-2">Status</th>
                        <th class="text-left px-4 py-2">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($withdrawals as $w)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-2 text-slate-600">{{ $w->created_at->format('d M Y, h:i A') }}</td>
                            <td class="px-4 py-2">{{ $w->user?->username ?? '—' }}</td>
                            <td class="px-4 py-2 uppercase text-xs">{{ $w->method }}</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($w->amount_paise / 100, 2) }}</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($w->tds_paise / 100, 2) }}</td>
                            <td class="px-4 py-2 text-right">₹{{ number_format($w->commission_paise / 100, 2) }}</td>
                            <td class="px-4 py-2 text-right font-semibold">₹{{ number_format($w->net_paise / 100, 2) }}</td>
                            <td class="px-4 py-2 text-xs text-slate-600">
                                @if ($w->method === 'upi')
                                    UPI: {{ $w->details['upi_id'] ?? '—' }}
                                @else
                                    {{ $w->details['account_name'] ?? '—' }} · {{ $w->details['account_no'] ?? '—' }} · {{ $w->details['ifsc'] ?? '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold
                                    {{ $w->status === 'paid' ? 'bg-green-100 text-green-800' : ($w->status === 'rejected' ? 'bg-red-100 text-red-800' : ($w->status === 'approved' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800')) }}">
                                    {{ ucfirst($w->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex gap-2">
                                    @if ($w->status === 'pending')
                                        <form method="POST" action="{{ route('hmkr.withdrawals.approve', $w) }}">
                                            @csrf
                                            <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('hmkr.withdrawals.reject', $w) }}">
                                            @csrf
                                            <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded text-xs hover:bg-red-700">Reject &amp; refund</button>
                                        </form>
                                    @elseif ($w->status === 'approved')
                                        <form method="POST" action="{{ route('hmkr.withdrawals.paid', $w) }}">
                                            @csrf
                                            <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700">Mark paid</button>
                                        </form>
                                    @else
                                        <span class="text-slate-400 text-xs">Done</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $withdrawals->links() }}</div>
    @else
        <p class="text-slate-500">No withdrawal requests yet.</p>
    @endif
</div>
@endsection

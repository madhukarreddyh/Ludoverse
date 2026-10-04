@extends('layouts.app')

@section('title', 'My Wallet — LudoVerse')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">My Wallet</h1>

    <div class="bg-indigo-700 text-white p-6 rounded-lg shadow mb-6">
        <p class="text-sm opacity-80">Available balance</p>
        <p class="text-4xl font-bold">₹{{ number_format($balance_paise / 100, 2) }}</p>
        <div class="mt-4 flex gap-3">
            <a href="{{ route('wallet.deposit') }}" class="bg-white text-indigo-700 px-4 py-2 rounded font-semibold hover:bg-indigo-50">Deposit</a>
            <a href="{{ route('wallet.withdraw') }}" class="bg-indigo-500 text-white px-4 py-2 rounded font-semibold hover:bg-indigo-400">Withdraw</a>
        </div>
    </div>

    <h2 class="text-xl font-bold mb-3">Transaction history</h2>
    @if ($entries->count())
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="text-left px-4 py-2">Date</th>
                        <th class="text-left px-4 py-2">Type</th>
                        <th class="text-right px-4 py-2">Amount</th>
                        <th class="text-right px-4 py-2">Balance after</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-2 text-slate-600">{{ $entry->created_at->format('d M Y, h:i A') }}</td>
                            <td class="px-4 py-2 capitalize">{{ str_replace('_', ' ', $entry->transaction_type) }}</td>
                            <td class="px-4 py-2 text-right font-semibold {{ $entry->amount_paise >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $entry->amount_paise >= 0 ? '+' : '−' }}₹{{ number_format(abs($entry->amount_paise) / 100, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right text-slate-600">₹{{ number_format($entry->new_balance_paise / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $entries->links() }}</div>
    @else
        <p class="text-slate-500">No transactions yet. Make your first deposit to get started.</p>
    @endif
</div>
@endsection

@extends('layouts.app')

@section('title', 'API Documentation — LudoVerse')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-2">LudoVerse API</h1>
    <p class="text-slate-600 mb-8">Auto-generated from the central <code class="bg-slate-100 px-1 rounded">ApiDocRegistry</code>. Also see the <a href="{{ route('api.docs.embedding') }}" class="text-indigo-700 hover:underline">iframe embedding guide</a>.</p>

    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-bold mb-3">Authentication</h2>
        <div class="text-sm space-y-3">
            <p><strong>Public API</strong> — two tokens, two jobs:</p>
            <ol class="list-decimal ml-6 space-y-1 text-slate-700">
                <li><strong>API key</strong> (issued by the LudoVerse admin, <code class="bg-slate-100 px-1 rounded">lv_pub_…</code>): identifies your integration. Send as <code class="bg-slate-100 px-1 rounded">Authorization: Bearer &lt;API_KEY&gt;</code> on <code class="bg-slate-100 px-1 rounded">POST /api/v1/public/player/login</code>.</li>
                <li><strong>Player token</strong> (<code class="bg-slate-100 px-1 rounded">lv_player_…</code>, valid 24h): identifies the player. Send as <code class="bg-slate-100 px-1 rounded">Authorization: Bearer &lt;PLAYER_TOKEN&gt;</code> on all game endpoints.</li>
            </ol>
            <p><strong>Partner API</strong> — <code class="bg-slate-100 px-1 rounded">lv_priv_…</code> private keys as Bearer, <em>plus</em> IP whitelisting. The private key can debit/credit real money: keep it server-side, never in client-side code, and rotate it if ever exposed.</p>
        </div>
    </div>

    @foreach ($endpoints as $endpoint)
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex flex-wrap items-center gap-3 mb-2">
                <span class="px-2 py-1 rounded text-xs font-bold {{ $endpoint['method'] === 'GET' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">{{ $endpoint['method'] }}</span>
                <code class="font-mono text-sm">{{ $endpoint['path'] }}</code>
            </div>
            <p class="text-sm text-slate-700 mb-2">{{ $endpoint['description'] }}</p>
            <p class="text-xs text-slate-500 mb-3">Auth: {{ $endpoint['auth'] }}</p>
            @if ($endpoint['params'])
                <h3 class="text-sm font-bold mb-1">Parameters</h3>
                <table class="w-full text-sm mb-3">
                    <tbody>
                        @foreach ($endpoint['params'] as $name => $desc)
                            <tr class="border-t border-slate-100">
                                <td class="py-1 pr-4 font-mono text-xs w-40">{{ $name }}</td>
                                <td class="py-1 text-slate-600">{{ $desc }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <h3 class="text-sm font-bold mb-1">Example</h3>
            <pre class="bg-slate-900 text-slate-100 rounded p-3 text-xs overflow-x-auto">{{ \App\Services\ApiDocRegistry::curlExample($endpoint) }}</pre>
            <h3 class="text-sm font-bold mt-3 mb-1">Response</h3>
            <pre class="bg-slate-50 border rounded p-3 text-xs overflow-x-auto">{{ $endpoint['response'] }}</pre>
        </div>
    @endforeach
</div>
@endsection

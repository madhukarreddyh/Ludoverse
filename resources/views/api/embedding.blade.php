@extends('layouts.app')

@section('title', 'Secure Iframe Embedding — LudoVerse API')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-2">Secure iframe embedding</h1>
    <p class="text-slate-600 mb-8">Let partners show a <strong>live, read-only</strong> Ludo board on their own site — without ever receiving game logic.</p>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-3">How it works</h2>
        <ol class="list-decimal ml-6 text-sm space-y-2 text-slate-700">
            <li><strong>Issue a token server-side.</strong> Using your API key, call <code class="bg-slate-100 px-1 rounded">EmbedTokenService::issue($apiKey, $ttlSeconds)</code>. The token is <code class="bg-slate-100 px-1 rounded">base64url(payload).base64url(HMAC-SHA256)</code> where the payload binds the key id and an expiry timestamp.</li>
            <li><strong>Embed the iframe.</strong> Drop the token into the iframe URL on your page:
                <pre class="bg-slate-900 text-slate-100 rounded p-3 text-xs overflow-x-auto mt-2">&lt;iframe src="https://YOUR_DOMAIN/embed/match/123?token=&lt;TOKEN&gt;"
  width="480" height="640" frameborder="0"
  allow="fullscreen" title="LudoVerse live board"&gt;&lt;/iframe&gt;</pre>
            </li>
            <li><strong>We verify every load.</strong> <code class="bg-slate-100 px-1 rounded">EmbedTokenService::verify()</code> checks the HMAC, the expiry, and that the key is still active. Tampered or expired tokens get a 403.</li>
        </ol>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-3">Trust model (read this)</h2>
        <ul class="list-disc ml-6 text-sm space-y-2 text-slate-700">
            <li>The iframe renders <strong>only the board state the server broadcasts</strong> — the same spectator-safe payload as the public watch page. Dice rolls, move validation, wallets and payouts never leave LudoVerse servers.</li>
            <li>Tokens are short-lived (default 1 hour). Issue a fresh one per page view; never hard-code a token into your HTML.</li>
            <li>The HMAC is keyed by a server secret bound to your key id — stealing a token is useless after expiry, and a stolen token grants no API access.</li>
            <li>For real-time updates inside the iframe, the embed page subscribes to the match's broadcast channel itself; your site needs no game code at all.</li>
        </ul>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-3">PHP example (partner server)</h2>
        <pre class="bg-slate-900 text-slate-100 rounded p-3 text-xs overflow-x-auto">$apiKey = \App\Models\ApiKey::find($keyId); // your private/public key row
$token = app(\App\Services\EmbedTokenService::class)->issue($apiKey, 3600);

// in your Blade template:
&lt;iframe src="{{ config('app.url') }}/embed/match/123?token=@{{ $token }}"&gt;&lt;/iframe&gt;</pre>
    </div>
</div>
@endsection

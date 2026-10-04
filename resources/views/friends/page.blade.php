@extends('layouts.app')

@section('title', 'Friends — LudoVerse')

@section('content')
<div class="max-w-xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-extrabold text-amber-400 mb-6">&#128101; Friends</h1>

    {{-- Add friend by game ID --}}
    <div class="card-dark p-4 mb-6">
        <h2 class="font-bold text-slate-100 mb-2">Add a friend</h2>
        <div class="flex gap-2">
            <input id="friend-game-id" type="text" maxlength="16" placeholder="Friend's game ID"
                   class="flex-1 min-h-[48px] bg-slate-900 border border-slate-700 rounded-xl px-4 text-slate-100 placeholder:text-slate-500 focus:outline-none focus:border-amber-500">
            <button id="friend-request-btn" class="btn-chunky btn-gold">Add</button>
        </div>
    </div>

    <h2 class="font-bold text-slate-100 mb-2">Your friends</h2>
    <div id="friends-list" class="grid gap-2">
        <p class="text-slate-500 text-sm">Loading&hellip;</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var list = document.getElementById('friends-list');

    function row(f) {
        var el = document.createElement('div');
        el.className = 'card-dark p-3 flex items-center gap-3';
        el.innerHTML =
            '<span class="w-10 h-10 rounded-xl bg-slate-700 flex items-center justify-center font-black text-amber-400">' +
                escapeHtml((f.name || '?').charAt(0).toUpperCase()) + '</span>' +
            '<div class="flex-1 min-w-0">' +
                '<p class="font-bold text-slate-100 truncate">' + escapeHtml(f.name || 'Player') + '</p>' +
                '<p class="text-xs text-slate-400">' + escapeHtml(f.game_id || '') + '</p>' +
            '</div>' +
            (f.online
                ? '<span class="text-xs font-bold text-green-300">&#128994; Online</span>'
                : '<span class="text-xs text-slate-500">Offline</span>');
        return el;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function load() {
        fetch('/friends', { headers: window.lvHeaders() })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                list.innerHTML = '';
                var friends = (j && j.friends) || [];
                if (!friends.length) {
                    list.innerHTML = '<p class="text-slate-500 text-sm">No friends yet — add one by game ID above.</p>';
                    return;
                }
                friends.forEach(function (f) { list.appendChild(row(f)); });
            })
            .catch(function () {
                list.innerHTML = '<p class="text-slate-500 text-sm">Could not load friends. Check your connection.</p>';
            });
    }

    document.getElementById('friend-request-btn').addEventListener('click', function () {
        var input = document.getElementById('friend-game-id');
        var gameId = input.value.trim();
        if (!gameId) { window.toast('Enter a game ID first.', 'info'); return; }
        fetch('/friends/request', {
            method: 'POST',
            headers: Object.assign(window.lvHeaders(), { 'Content-Type': 'application/json' }),
            body: JSON.stringify({ game_id: gameId }),
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
        .then(function (res) {
            if (res.ok) {
                window.toast('Friend request sent.', 'success');
                input.value = '';
            } else {
                var err = (res.body && res.body.error) || {};
                window.toast(err.message || 'Could not send the request.', 'error');
            }
        })
        .catch(function () { window.toast('Network error. Check your connection.', 'error'); });
    });

    load();
})();
</script>
@endpush

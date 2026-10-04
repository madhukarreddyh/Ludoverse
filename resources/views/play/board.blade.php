@extends('layouts.app')

@section('title', 'Match #' . $match->id . ' — LudoVerse')

@section('content')
<div class="max-w-3xl mx-auto px-3 py-4">
    {{-- Match header bar --}}
    <div class="card-dark px-4 py-3 mb-3 flex items-center justify-between gap-2 flex-wrap">
        <div class="flex items-center gap-2">
            <span id="live-dot" class="hidden items-center gap-1.5 text-xs font-extrabold text-red-400">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse inline-block"></span> LIVE
            </span>
            <span class="text-sm font-bold text-slate-200">#{{ $match->id }} &middot; {{ $match->mode }}</span>
            <span class="text-sm font-bold text-amber-400">&#8377;{{ number_format($match->bet_paise / 100, 2) }}</span>
        </div>
        <div class="flex items-center gap-2">
            @if ($spectator)
                <span class="text-xs font-bold text-sky-300 bg-sky-900/60 border border-sky-700 rounded-full px-3 py-1.5">&#128065; Spectating</span>
            @endif
            @if (! $spectator)
                <button id="exit-btn" class="btn-chunky btn-danger !min-h-[40px] !py-1.5 !px-3 text-xs">Exit</button>
            @endif
        </div>
    </div>

    {{-- Turn indicator + countdown --}}
    <div class="card-dark px-4 py-3 mb-3">
        <p id="turn-label" class="text-center font-extrabold text-lg text-slate-200">Loading match&hellip;</p>
        <div class="mt-2 h-2.5 bg-slate-800 rounded-full overflow-hidden">
            <div id="turn-bar" class="h-full bg-gradient-to-r from-amber-500 to-amber-300 rounded-full transition-all duration-500" style="width: 0%"></div>
        </div>
        <p id="turn-secs" class="text-center text-xs text-slate-400 mt-1"></p>
    </div>

    {{-- Board --}}
    <div class="relative">
        <div id="ludo-board" class="relative w-full aspect-square select-none" aria-label="Ludo board"></div>
        <div id="token-layer" class="absolute inset-0 pointer-events-none"></div>

        {{-- Winner overlay --}}
        <div id="winner-banner" class="hidden absolute inset-0 z-10 flex-col items-center justify-center bg-slate-950/85 rounded-2xl text-center p-6">
            <p class="text-5xl mb-3" aria-hidden="true">&#127942;</p>
            <p id="winner-text" class="text-2xl font-extrabold text-amber-400 mb-1"></p>
            <p id="winner-pot" class="text-slate-300 mb-5"></p>
            <a href="{{ route('play.lobby') }}" class="btn-chunky btn-gold">Back to Lobby</a>
        </div>

        {{-- Waiting overlay --}}
        <div id="waiting-banner" class="hidden absolute inset-0 z-10 flex-col items-center justify-center bg-slate-950/70 rounded-2xl text-center p-6">
            <p class="text-4xl mb-3 animate-bounce" aria-hidden="true">&#9203;</p>
            <p class="text-xl font-extrabold text-slate-100">Waiting for players&hellip;</p>
            <p id="waiting-sub" class="text-slate-400 text-sm mt-1"></p>
        </div>
    </div>

    {{-- Controls --}}
    @if (! $spectator)
        <div class="flex items-center justify-center gap-4 mt-4">
            <div id="dice-face" class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-900 flex items-center justify-center text-4xl font-black shadow-lg" aria-label="Dice">&#127922;</div>
            <button id="roll-btn" class="btn-chunky btn-gold text-xl px-10" disabled>ROLL</button>
        </div>
        <p id="roll-hint" class="text-center text-xs text-slate-400 mt-2">Wait for your turn to roll.</p>
    @endif

    {{-- Players / scores --}}
    <h2 class="text-lg font-extrabold text-slate-100 mt-6 mb-2">Players</h2>
    <div id="players-panel" class="grid gap-2 sm:grid-cols-2"></div>

    {{-- Event feed --}}
    <h2 class="text-lg font-extrabold text-slate-100 mt-6 mb-2">Match feed</h2>
    <div id="event-feed" class="card-dark p-3 min-h-[64px] max-h-44 overflow-y-auto text-sm text-slate-300 space-y-1.5">
        <p class="text-slate-500">Big moments will appear here.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var MATCH_ID = @json($match->id);
    var IS_SPECTATOR = @json($spectator);
    var MY_USER_ID = @json(auth()->id());
    var MY_COLOR = @json($myColor);
    var PLAYER_NAMES = @json($playerNames);
    var POLL_URL = IS_SPECTATOR ? '/play/match/' + MATCH_ID + '/watch' : '/play/match/' + MATCH_ID;
    var POST_BASE = '/play/match/' + MATCH_ID;

    /* ---------- Board geometry (mirrors LudoEngine track order) ---------- */
    var COLOR_STARTS = { red: 0, green: 13, yellow: 26, blue: 39, orange: 6, purple: 19, teal: 32, pink: 45 };
    var COLOR_CSS = { red: '#ef4444', green: '#22c55e', yellow: '#eab308', blue: '#3b82f6', orange: '#f97316', purple: '#a855f7', teal: '#14b8a6', pink: '#ec4899' };
    var COLOR_LIGHT = { red: '#fecaca', green: '#bbf7d0', yellow: '#fef08a', blue: '#bfdbfe', orange: '#fed7aa', purple: '#e9d5ff', teal: '#99f6e4', pink: '#fbcfe8' };
    var COLOR_DARK = { red: '#7f1d1d', green: '#14532d', yellow: '#713f12', blue: '#1e3a8a', orange: '#7c2d12', purple: '#581c87', teal: '#134e4a', pink: '#831843' };

    var TRACK = [
        [6,1],[6,2],[6,3],[6,4],[6,5],
        [5,6],[4,6],[3,6],[2,6],[1,6],[0,6],
        [0,7],[0,8],
        [1,8],[2,8],[3,8],[4,8],[5,8],
        [6,9],[6,10],[6,11],[6,12],[6,13],[6,14],
        [7,14],
        [8,14],
        [8,13],[8,12],[8,11],[8,10],[8,9],
        [9,8],[10,8],[11,8],[12,8],[13,8],[14,8],
        [14,7],[14,6],
        [13,6],[12,6],[11,6],[10,6],[9,6],
        [8,5],[8,4],[8,3],[8,2],[8,1],[8,0],
        [7,0],[6,0]
    ];
    var SAFE = { 0: 1, 8: 1, 13: 1, 21: 1, 26: 1, 34: 1, 39: 1, 47: 1 };
    var CLASSIC = {
        red:    { corner: [0, 0], stretch: [[7,1],[7,2],[7,3],[7,4],[7,5]] },
        green:  { corner: [0, 9], stretch: [[1,7],[2,7],[3,7],[4,7],[5,7]] },
        yellow: { corner: [9, 9], stretch: [[7,13],[7,12],[7,11],[7,10],[7,9]] },
        blue:   { corner: [9, 0], stretch: [[13,7],[12,7],[11,7],[10,7],[9,7]] }
    };

    var TRACK_INDEX = {};
    TRACK.forEach(function (cell, abs) { TRACK_INDEX[cell[0] + ',' + cell[1]] = abs; });
    var ABS_START_COLOR = {};
    Object.keys(COLOR_STARTS).forEach(function (c) { ABS_START_COLOR[COLOR_STARTS[c]] = c; });
    var STRETCH_INDEX = {};
    Object.keys(CLASSIC).forEach(function (c) {
        CLASSIC[c].stretch.forEach(function (cell) { STRETCH_INDEX[cell[0] + ',' + cell[1]] = c; });
    });

    function inCorner(r, c, corner) {
        return r >= corner[0] && r < corner[0] + 6 && c >= corner[1] && c < corner[1] + 6;
    }

    /* ---------- Static board render ---------- */
    function renderStaticBoard() {
        var board = document.getElementById('ludo-board');
        board.innerHTML = '';
        var grid = document.createElement('div');
        grid.style.cssText = 'position:absolute;inset:0;display:grid;grid-template-columns:repeat(15,1fr);grid-template-rows:repeat(15,1fr);border-radius:1rem;overflow:hidden;border:2px solid #334155;';

        var corners = Object.keys(CLASSIC).map(function (c) { return { color: c, corner: CLASSIC[c].corner }; });

        for (var r = 0; r < 15; r++) {
            for (var c = 0; c < 15; c++) {
                var cell = document.createElement('div');
                var key = r + ',' + c;
                var style = 'display:flex;align-items:center;justify-content:center;font-size:0.55rem;';
                var html = '';

                var cornerHit = null;
                corners.forEach(function (k) { if (inCorner(r, c, k.corner)) cornerHit = k; });

                if (cornerHit) {
                    var inner = r > cornerHit.corner[0] && r < cornerHit.corner[0] + 5 && c > cornerHit.corner[1] && c < cornerHit.corner[1] + 5;
                    style += 'background:' + (inner ? '#f8fafc' : COLOR_CSS[cornerHit.color]) + ';';
                    if (!inner) style += 'opacity:0.92;';
                } else if (TRACK_INDEX[key] !== undefined) {
                    var abs = TRACK_INDEX[key];
                    if (ABS_START_COLOR[abs] !== undefined) {
                        var sc = ABS_START_COLOR[abs];
                        style += 'background:' + COLOR_CSS[sc] + ';color:#fff;font-weight:900;';
                        html = '&#9733;';
                    } else if (SAFE[abs]) {
                        style += 'background:#fde68a;color:#92400e;font-weight:900;';
                        html = '&#9733;';
                    } else {
                        style += 'background:#e2e8f0;border:1px solid #cbd5e1;';
                    }
                } else if (STRETCH_INDEX[key] !== undefined) {
                    style += 'background:' + COLOR_LIGHT[STRETCH_INDEX[key]] + ';border:1px solid #cbd5e1;';
                } else if (r >= 6 && r <= 8 && c >= 6 && c <= 8) {
                    style += 'background:linear-gradient(135deg,#fbbf24,#d97706);color:#0f172a;font-weight:900;';
                    if (r === 7 && c === 7) html = 'HOME';
                } else {
                    style += 'background:#0f172a;';
                }

                cell.style.cssText = style;
                cell.innerHTML = html;
                grid.appendChild(cell);
            }
        }
        board.appendChild(grid);
    }

    /* ---------- Token positioning ---------- */
    // Base slots for classic colors: 4 positions inside the white inner panel.
    function baseSlot(color, idx) {
        var cr = CLASSIC[color].corner[0], cc = CLASSIC[color].corner[1];
        var spots = [[1.9, 1.9], [1.9, 3.1], [3.1, 1.9], [3.1, 3.1]];
        return { r: cr + spots[idx][0], c: cc + spots[idx][1], size: 4.6 };
    }

    function tokenCell(color, steps, tokenIdx) {
        if (steps === -1) {
            if (CLASSIC[color]) return baseSlot(color, tokenIdx);
            return { bench: 'base' };
        }
        if (steps <= 50) {
            var cell = TRACK[(COLOR_STARTS[color] + steps) % 52];
            return { r: cell[0], c: cell[1], size: 5.6 };
        }
        if (steps <= 55) {
            if (CLASSIC[color]) {
                var s = CLASSIC[color].stretch[steps - 51];
                return { r: s[0], c: s[1], size: 5.2 };
            }
            return { bench: 'stretch' };
        }
        return { r: 7, c: 7, size: 4.2, home: true }; // finished: center
    }

    function renderTokens(state) {
        var layer = document.getElementById('token-layer');
        layer.innerHTML = '';
        layer.style.pointerEvents = 'none';

        var board = state.board || {};
        var myTurn = !IS_SPECTATOR && state.status === 'running' &&
            parseInt(state.current_turn_user_id, 10) === parseInt(MY_USER_ID, 10);
        var legal = (myTurn && state.pending_dice) ? (state.legal_moves || []) : [];

        // Group track/center tokens by cell for stacking offsets.
        var groups = {};
        Object.keys(board).forEach(function (color) {
            (board[color] || []).forEach(function (steps, idx) {
                var pos = tokenCell(color, steps, idx);
                if (pos.bench) return;
                var k = pos.r.toFixed(2) + ',' + pos.c.toFixed(2);
                (groups[k] = groups[k] || []).push({ color: color, idx: idx, pos: pos, steps: steps });
            });
        });

        var stackOffsets = [[-1.1, -1.1], [1.1, -1.1], [-1.1, 1.1], [1.1, 1.1], [0, 0]];
        Object.keys(groups).forEach(function (k) {
            var g = groups[k];
            g.forEach(function (t, n) {
                var off = g.length > 1 ? stackOffsets[n % stackOffsets.length] : [0, 0];
                var el = document.createElement('div');
                var isLegal = t.color === MY_COLOR && legal.indexOf(t.idx) !== -1;
                var size = g.length > 1 ? 4.4 : t.pos.size;
                el.style.cssText =
                    'position:absolute;width:' + size + '%;aspect-ratio:1;border-radius:50%;' +
                    'left:' + ((t.pos.c + 0.5) / 15 * 100 + off[0]) + '%;' +
                    'top:' + ((t.pos.r + 0.5) / 15 * 100 + off[1]) + '%;' +
                    'transform:translate(-50%,-50%);' +
                    'background:radial-gradient(circle at 35% 30%, ' + COLOR_LIGHT[t.color] + ', ' + COLOR_CSS[t.color] + ' 70%);' +
                    'border:2px solid ' + COLOR_DARK[t.color] + ';' +
                    'box-shadow:0 2px 6px rgba(0,0,0,.5);' +
                    'display:flex;align-items:center;justify-content:center;' +
                    'color:' + COLOR_DARK[t.color] + ';font-weight:900;font-size:0.6rem;';
                el.textContent = t.idx + 1;
                el.title = t.color + ' token ' + (t.idx + 1);
                if (isLegal) {
                    el.classList.add('token-legal');
                    el.style.pointerEvents = 'auto';
                    el.addEventListener('click', function () { doMove(t.idx); });
                }
                layer.appendChild(el);
            });
        });
    }

    /* ---------- Panels ---------- */
    function playerName(p) {
        if (p.user_id !== null && p.user_id !== undefined) {
            return PLAYER_NAMES[p.user_id] || ('Player ' + p.user_id);
        }
        return PLAYER_NAMES['bot_' + p.id] || 'Bot';
    }

    function renderPlayers(state) {
        var panel = document.getElementById('players-panel');
        panel.innerHTML = '';
        var board = state.board || {};

        (state.players || []).forEach(function (p) {
            var tokens = board[p.color] || [];
            var home = tokens.filter(function (s) { return s === 56; }).length;
            var base = tokens.filter(function (s) { return s === -1; }).length;
            var isTurn = parseInt(state.current_turn_user_id, 10) === parseInt(p.user_id, 10) && state.status === 'running';

            var card = document.createElement('div');
            card.className = 'card-dark p-3 flex items-center gap-3' + (isTurn ? ' border-amber-400' : '');
            card.innerHTML =
                '<span class="w-6 h-6 rounded-full shrink-0 border-2 border-slate-950" style="background:' + (COLOR_CSS[p.color] || '#888') + '"></span>' +
                '<div class="flex-1 min-w-0">' +
                    '<p class="font-bold text-slate-100 truncate">' + escapeHtml(playerName(p)) +
                        (p.color === MY_COLOR ? ' <span class="text-amber-400 text-xs">(you)</span>' : '') + '</p>' +
                    '<p class="text-xs text-slate-400">Score ' + (p.score || 0) +
                        ' &middot; &#127968; ' + home + '/4 home' +
                        (base ? ' &middot; ' + base + ' in base' : '') + '</p>' +
                '</div>' +
                (isTurn ? '<span class="text-amber-400 text-xs font-extrabold animate-pulse">TURN</span>' : '');
            panel.appendChild(card);
        });
    }

    function renderTurn(state) {
        var label = document.getElementById('turn-label');
        var dot = document.getElementById('live-dot');
        var rollBtn = document.getElementById('roll-btn');
        var hint = document.getElementById('roll-hint');

        if (state.status === 'running') {
            dot.classList.remove('hidden');
            dot.classList.add('inline-flex');
        } else {
            dot.classList.add('hidden');
            dot.classList.remove('inline-flex');
        }

        if (state.status === 'waiting') {
            var seated = (state.players || []).length;
            label.textContent = 'Waiting for players… (' + seated + ' seated)';
            document.getElementById('waiting-banner').style.display = 'flex';
            document.getElementById('waiting-sub').textContent = seated + ' player(s) at the table.';
            if (rollBtn) rollBtn.disabled = true;
            return;
        }
        document.getElementById('waiting-banner').style.display = 'none';

        if (state.status === 'finished' || state.status === 'cancelled') {
            label.textContent = state.status === 'finished' ? 'Match finished' : 'Match cancelled';
            if (rollBtn) rollBtn.disabled = true;
            showWinner(state);
            return;
        }

        var me = parseInt(state.current_turn_user_id, 10) === parseInt(MY_USER_ID, 10);
        if (IS_SPECTATOR || !me) {
            var turnPlayer = (state.players || []).find(function (p) { return parseInt(p.user_id, 10) === parseInt(state.current_turn_user_id, 10); });
            label.innerHTML = '&#9203; Waiting on <span class="text-amber-400">' + escapeHtml(turnPlayer ? playerName(turnPlayer) : 'next player') + '</span>';
        } else if (state.pending_dice) {
            label.innerHTML = '&#127922; You rolled <span class="text-amber-400">' + state.pending_dice + '</span> — tap a glowing token';
        } else {
            label.innerHTML = '<span class="text-amber-400">Your turn!</span> Roll the dice &#127922;';
        }

        if (rollBtn) {
            var canRoll = !IS_SPECTATOR && me && !state.pending_dice;
            rollBtn.disabled = !canRoll;
            if (hint) hint.textContent = canRoll ? 'Tap ROLL — you have 30 seconds.' : (me ? 'Choose a glowing token to move.' : 'Wait for your turn to roll.');
        }
    }

    var DICE_FACES = ['\u2680', '\u2681', '\u2682', '\u2683', '\u2684', '\u2685'];

    function showWinner(state) {
        var banner = document.getElementById('winner-banner');
        banner.style.display = 'flex';
        var winner = (state.players || []).find(function (p) { return parseInt(p.user_id, 10) === parseInt(state.winner_user_id, 10); });
        document.getElementById('winner-text').textContent =
            winner ? '\uD83C\uDFC6 ' + playerName(winner) + ' wins!' : 'Match over';
        var pot = (state.bet_paise || 0) * (state.players || []).length;
        document.getElementById('winner-pot').textContent =
            'Prize pot \u20B9' + (pot / 100).toFixed(2) + (state.winning_team ? ' · winning team ' + state.winning_team : '');
    }

    /* ---------- Countdown ---------- */
    var lastDeadline = null;
    setInterval(function () {
        var bar = document.getElementById('turn-bar');
        var secs = document.getElementById('turn-secs');
        if (!lastDeadline) { bar.style.width = '0%'; secs.textContent = ''; return; }
        var remaining = Math.max(0, Math.round((new Date(lastDeadline).getTime() - Date.now()) / 1000));
        bar.style.width = Math.min(100, remaining / 30 * 100) + '%';
        bar.style.background = remaining <= 10 ? '#ef4444' : '';
        secs.textContent = remaining > 0 ? remaining + 's left on this turn' : 'Turn expiring…';
    }, 500);

    /* ---------- Event feed ---------- */
    function feedEvents(events) {
        var feed = document.getElementById('event-feed');
        var placeholder = feed.querySelector('.text-slate-500');
        if (placeholder) placeholder.remove();
        events.forEach(function (e) {
            var line = document.createElement('p');
            if (e.type === 'capture') {
                line.innerHTML = '\uD83D\uDD25 <b class="text-amber-300">' + escapeHtml(e.attacker_color) + '</b> captures <b class="text-red-300">' + escapeHtml(e.victim_color) + '</b>\u2019s token!';
            } else if (e.type === 'home') {
                line.innerHTML = '\uD83C\uDFE0 <b class="text-green-300">' + escapeHtml(e.color) + '</b> brings a token home!';
            } else if (e.type === 'leave_base') {
                line.innerHTML = '\uD83D\uDEAA <b class="text-slate-100">' + escapeHtml(e.color) + '</b> leaves base.';
            } else {
                return;
            }
            feed.prepend(line);
        });
        while (feed.children.length > 30) feed.removeChild(feed.lastChild);
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    /* ---------- Actions ---------- */
    function doMove(tokenIdx) {
        fetch(POST_BASE + '/move', {
            method: 'POST',
            headers: Object.assign(window.lvHeaders(), { 'Content-Type': 'application/json' }),
            body: JSON.stringify({ token_index: tokenIdx }),
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
        .then(function (res) {
            if (!res.ok) {
                var err = (res.body && res.body.error) || {};
                window.toast(err.message || 'Move failed.', 'error');
                return;
            }
            feedEvents(res.body.events || []);
            refresh();
        })
        .catch(function () { window.toast('Network error. Check your connection.', 'error'); });
    }

    var rollBtnEl = document.getElementById('roll-btn');
    if (rollBtnEl) {
        rollBtnEl.addEventListener('click', function () {
            rollBtnEl.disabled = true;
            fetch(POST_BASE + '/roll', { method: 'POST', headers: window.lvHeaders() })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (!res.ok) {
                    var err = (res.body && res.body.error) || {};
                    window.toast(err.message || 'Roll failed.', 'error');
                    refresh();
                    return;
                }
                var diceEl = document.getElementById('dice-face');
                if (res.body.dice) diceEl.textContent = DICE_FACES[res.body.dice - 1];
                if (res.body.auto_passed) window.toast('No legal moves — turn passes.', 'info');
                if (res.body.forfeited) window.toast('Three sixes in a row — turn forfeited.', 'info');
                refresh();
            })
            .catch(function () {
                window.toast('Network error. Check your connection.', 'error');
                rollBtnEl.disabled = false;
            });
        });
    }

    var exitBtn = document.getElementById('exit-btn');
    if (exitBtn) {
        exitBtn.addEventListener('click', function () {
            if (!confirm('Exit this match? You will forfeit your stake.')) return;
            fetch(POST_BASE + '/exit', { method: 'POST', headers: window.lvHeaders() })
            .then(function () { window.location.href = '/play'; })
            .catch(function () { window.toast('Could not exit. Try again.', 'error'); });
        });
    }

    /* ---------- Polling ---------- */
    var pollTimer = null;
    var firstLoad = true;

    function refresh() {
        return fetch(POLL_URL, { headers: window.lvHeaders() })
            .then(function (r) {
                if (!r.ok) throw new Error('poll ' + r.status);
                return r.json();
            })
            .then(function (s) {
                firstLoad = false;
                lastDeadline = s.turn_deadline_at || null;
                renderTokens(s);
                renderPlayers(s);
                renderTurn(s);
                if (s.pending_dice) {
                    document.getElementById('dice-face').textContent = DICE_FACES[s.pending_dice - 1];
                }
                if ((s.status === 'finished' || s.status === 'cancelled') && pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            })
            .catch(function () {
                if (firstLoad) {
                    document.getElementById('turn-label').textContent = 'Could not load the match. Check your connection.';
                }
            });
    }

    renderStaticBoard();
    refresh();
    pollTimer = setInterval(function () { if (!document.hidden) refresh(); }, 2500);
})();
</script>
@endpush

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotice;
use App\Models\CheatLog;
use App\Models\FraudFlag;
use App\Models\LudoMatch;
use App\Models\MatchPlayer;
use App\Models\Setting;
use App\Models\User;
use App\Services\RiskService;
use Illuminate\View\View;

/**
 * Admin home (/hmkr): platform health at a glance.
 */
class DashboardController extends Controller
{
    public function __construct(protected RiskService $risk) {}

    public function index(): View
    {
        $numbers = $this->risk->dailyNumbers();

        // Bot-match %: share of the last 100 matches that seated ≥ 1 bot.
        $last100 = LudoMatch::orderByDesc('id')->limit(100)->pluck('id');
        $botMatches = $last100->isEmpty() ? 0 : MatchPlayer::whereIn('match_id', $last100)
            ->where('is_bot', true)
            ->distinct('match_id')
            ->count('match_id');
        $botPct = $last100->isEmpty() ? 0 : round($botMatches / $last100->count() * 100, 1);

        return view('admin.dashboard', [
            'onlinePlayers' => User::where('last_seen_at', '>=', now()->subMinutes(5))->count(),
            'runningMatches' => LudoMatch::where('status', 'running')->count(),
            'waitingMatches' => LudoMatch::where('status', 'waiting')->count(),
            'depositsToday' => $numbers['deposits'],
            'withdrawalsToday' => $numbers['withdrawals'],
            'profitToday' => $numbers['profit'],
            'botMatchPct' => $botPct,
            'openFraudAlerts' => FraudFlag::where('status', 'open')->count(),
            'recentCheatLogs' => CheatLog::with('user')->orderByDesc('id')->limit(10)->get(),
            'notices' => AdminNotice::orderByDesc('id')->limit(5)->get(),
            'unreadNotices' => AdminNotice::unread()->count(),
            'botDifficulty' => Setting::get('bot_difficulty', 'medium'),
            'maintenanceMode' => Setting::bool('maintenance_mode'),
            'clientVersion' => Setting::get('client_version', '1.0.0'),
        ]);
    }

    /**
     * Mark a notice as read.
     */
    public function readNotice(AdminNotice $notice)
    {
        $notice->forceFill(['is_read' => true])->save();

        return back();
    }

    /**
     * Maintenance-mode toggle from the dashboard.
     */
    public function maintenance(\Illuminate\Http\Request $request)
    {
        Setting::set('maintenance_mode', $request->boolean('enabled') ? '1' : '0');

        return back()->with('status', 'Maintenance mode '.($request->boolean('enabled') ? 'ENABLED — public pages now serve 503.' : 'disabled.'));
    }
}

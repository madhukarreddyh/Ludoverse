<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\TableManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Liquidity manager admin: "auto" mode follows the online-count ladder,
 * manual mode uses the checked bet levels.
 */
class TableController extends Controller
{
    public const LEVELS = [500, 1000, 5000, 10000, 50000];

    public function edit(): View
    {
        return view('admin.tables.edit', [
            'auto' => Setting::bool('tables_auto_mode'),
            'manual' => json_decode((string) Setting::get('tables_manual_bets', '["500","1000"]'), true) ?: [],
            'levels' => self::LEVELS,
            'online' => TableManager::onlineCount(),
            'current' => TableManager::currentAllowedBets(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_mode' => ['nullable', 'boolean'],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['integer', 'in:500,1000,5000,10000,50000'],
        ]);

        Setting::set('tables_auto_mode', $request->boolean('auto_mode') ? '1' : '0');

        $levels = array_values(array_unique(array_map('intval', $data['levels'] ?? [])));
        sort($levels);
        if ($levels === []) {
            $levels = [500, 1000]; // never close every table by accident
        }
        Setting::set('tables_manual_bets', json_encode(array_map('strval', $levels)));

        return back()->with('status', 'Table settings saved.');
    }
}

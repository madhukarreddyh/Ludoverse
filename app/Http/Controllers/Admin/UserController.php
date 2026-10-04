<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheatLog;
use App\Models\Device;
use App\Models\FraudFlag;
use App\Models\LudoMatch;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin user management: list/search, detail (wallet, matches, devices,
 * cheat + fraud history), suspend/unsuspend, freeze/unfreeze wallet.
 */
class UserController extends Controller
{
    public function __construct(protected WalletService $wallets) {}

    public function index(Request $request): View
    {
        $query = User::query()->orderByDesc('id');

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('game_id', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return view('admin.users.index', [
            'users' => $query->paginate(25)->withQueryString(),
            'q' => $search,
            'status' => $status,
        ]);
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user,
            'balance' => $this->wallets->balance($user),
            'available' => $this->wallets->availableBalance($user),
            'locked' => $this->wallets->lockedBalance($user),
            'locks' => $user->bonusLocks()->orderByDesc('id')->limit(20)->get(),
            'devices' => $user->devices()->orderByDesc('created_at')->get(),
            'matches' => LudoMatch::whereHas('players', fn ($q) => $q->where('user_id', $user->id))
                ->orderByDesc('id')->limit(20)->get(),
            'cheatLogs' => CheatLog::where('user_id', $user->id)->orderByDesc('id')->limit(20)->get(),
            'fraudFlags' => FraudFlag::where('user_id', $user->id)->orderByDesc('id')->limit(20)->get(),
            'ledger' => $user->walletEntries()->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function suspend(User $user): RedirectResponse
    {
        $user->forceFill(['status' => 'suspended'])->save();

        return back()->with('status', "Account @{$user->username} suspended.");
    }

    public function unsuspend(User $user): RedirectResponse
    {
        $user->forceFill(['status' => 'active'])->save();

        return back()->with('status', "Account @{$user->username} re-activated.");
    }

    public function freeze(User $user): RedirectResponse
    {
        $user->forceFill(['status' => 'frozen'])->save();

        return back()->with('status', "Wallet of @{$user->username} frozen — debits are now blocked.");
    }

    public function unfreeze(User $user): RedirectResponse
    {
        $user->forceFill(['status' => 'active'])->save();

        return back()->with('status', "Wallet of @{$user->username} unfrozen.");
    }

    public function destroyDevice(Device $device): RedirectResponse
    {
        $device->delete();

        return back()->with('status', 'Device link removed. The user can link this device again on next login.');
    }
}

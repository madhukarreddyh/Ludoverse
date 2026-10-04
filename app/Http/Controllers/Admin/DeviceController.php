<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BannedDevice;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Device security admin: every linked device, ban/unban by hash.
 * Banning a hash blocks ALL signup/login attempts from it.
 */
class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $query = Device::with('user')->orderByDesc('created_at');

        if ($search = $request->string('q')->toString()) {
            $query->where('device_hash', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($q) => $q->where('username', 'like', "%{$search}%"));
        }

        $banned = BannedDevice::pluck('device_hash')->all();

        return view('admin.devices.index', [
            'devices' => $query->paginate(25)->withQueryString(),
            'banned' => array_flip($banned),
            'bannedList' => BannedDevice::orderByDesc('id')->paginate(25, ['*'], 'banned_page'),
            'q' => $search,
        ]);
    }

    public function ban(Request $request): RedirectResponse
    {
        $data = $request->validate(['device_hash' => ['required', 'string', 'size:64']]);

        BannedDevice::firstOrCreate(
            ['device_hash' => strtolower($data['device_hash'])],
            ['reason' => 'manual ban by admin']
        );

        return back()->with('status', 'Device banned.');
    }

    public function unban(BannedDevice $bannedDevice): RedirectResponse
    {
        $bannedDevice->delete();

        return back()->with('status', 'Device unbanned.');
    }
}

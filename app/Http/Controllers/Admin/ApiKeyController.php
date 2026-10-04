<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * API key management. The plain key is shown ONCE (flashed to the very
 * next page view after generation/regeneration) — only the sha256 hash
 * is ever stored.
 */
class ApiKeyController extends Controller
{
    public function index(): View
    {
        return view('admin.api-keys.index', [
            'keys' => ApiKey::orderByDesc('id')->paginate(25),
            'plainKey' => session('plain_key'),
            'plainKeyName' => session('plain_key_name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:public,private'],
            // Private keys SHOULD be IP-whitelisted: debit/credit is powerful.
            'ip_whitelist' => ['nullable', 'string', 'max:500'],
        ]);

        $whitelist = $this->parseWhitelist($data['ip_whitelist'] ?? null);

        [$key, $plain] = ApiKey::generate($data['name'], $data['type'], $whitelist);

        return redirect()->route('hmkr.api-keys.index')->with([
            'plain_key' => $plain,
            'plain_key_name' => $key->name,
            'status' => "Key '{$key->name}' created — copy it NOW, it will never be shown again.",
        ]);
    }

    public function disable(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->forceFill(['is_active' => false])->save();

        return back()->with('status', "Key '{$apiKey->name}' disabled (requests now get 403).");
    }

    public function enable(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->forceFill(['is_active' => true])->save();

        return back()->with('status', "Key '{$apiKey->name}' re-enabled.");
    }

    public function regenerate(ApiKey $apiKey): RedirectResponse
    {
        $plain = 'lv_'.($apiKey->type === 'private' ? 'priv_' : 'pub_').\Illuminate\Support\Str::random(40);
        $apiKey->forceFill([
            'key_prefix' => substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
        ])->save();

        return redirect()->route('hmkr.api-keys.index')->with([
            'plain_key' => $plain,
            'plain_key_name' => $apiKey->name,
            'status' => "Key '{$apiKey->name}' regenerated — copy the new key NOW.",
        ]);
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->delete();

        return back()->with('status', "Key '{$apiKey->name}' deleted.");
    }

    public function logs(ApiKey $apiKey): View
    {
        return view('admin.api-keys.logs', [
            'key' => $apiKey,
            'logs' => $apiKey->logs()->orderByDesc('id')->paginate(50),
        ]);
    }

    /**
     * Comma/space separated IP list -> array. Invalid entries are dropped
     * rather than failing the whole form.
     */
    protected function parseWhitelist(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $ips = preg_split('/[\s,]+/', trim($raw));
        $valid = array_values(array_filter($ips, fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP)));

        return $valid === [] ? null : $valid;
    }
}

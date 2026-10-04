<?php

namespace App\Services;

/**
 * Single source of truth for the public + partner API surface.
 * GET /api/docs renders this registry as a Blade page (auth instructions
 * + curl examples); every API route added to routes/api.php MUST be
 * registered here or it won't appear in the docs.
 */
class ApiDocRegistry
{
    /**
     * @return array<int, array{method:string, path:string, auth:string, description:string, params:array<string,string>, response:string}>
     */
    public static function all(): array
    {
        return [
            [
                'method' => 'POST',
                'path' => '/api/v1/public/player/login',
                'auth' => 'Public API key (Bearer)',
                'description' => 'Log a player in with game_id or email+password. Returns a player token (valid 24h) for the game endpoints below.',
                'params' => [
                    'game_id' => 'string, optional — the player\'s game_id (if email omitted)',
                    'email' => 'string, optional — account email (if game_id omitted)',
                    'password' => 'string, required with email',
                ],
                'response' => '{"player_token":"lv_player_...","expires_at":"...","player":{"id":1,"game_id":"LV123","username":"..."}}',
            ],
            [
                'method' => 'GET',
                'path' => '/api/v1/public/wallet/balance',
                'auth' => 'Player token (Bearer)',
                'description' => 'Ledger balance, locked bonus and spendable balance, all in paise.',
                'params' => [],
                'response' => '{"balance_paise":15000,"locked_paise":10000,"available_paise":5000}',
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/public/match/start',
                'auth' => 'Player token (Bearer)',
                'description' => 'Join matchmaking (or create the table) for a mode + bet. Bet must be an open table level.',
                'params' => [
                    'mode' => 'string, required — one of 1v1, 2v2, 3v3, 4v4',
                    'bet' => 'integer, required — bet in paise (e.g. 500 = ₹5)',
                ],
                'response' => '{"match_id":12,"status":"waiting","bet_paise":500}',
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/public/match/join',
                'auth' => 'Player token (Bearer)',
                'description' => 'Join a specific waiting match by id.',
                'params' => ['match_id' => 'integer, required'],
                'response' => '{"match_id":12,"status":"waiting"}',
            ],
            [
                'method' => 'GET',
                'path' => '/api/v1/public/match/{id}/result',
                'auth' => 'Player token (Bearer)',
                'description' => 'Finished-match result: status, winner, scores, payouts.',
                'params' => [],
                'response' => '{"id":12,"status":"finished","winner_user_id":5,"scores":{...},"payouts":[...]}',
            ],
            [
                'method' => 'GET',
                'path' => '/api/v1/partner/tournaments',
                'auth' => 'Private API key (Bearer) + IP whitelist',
                'description' => 'List tournaments (id, name, status, entry fee).',
                'params' => [],
                'response' => '{"tournaments":[{"id":1,"name":"...","status":"open"}]}',
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/partner/tournaments/{id}/join',
                'auth' => 'Private API key (Bearer) + IP whitelist',
                'description' => 'Enter a player into a tournament (entry fee debited).',
                'params' => ['user_id' => 'integer, required — the player to enter'],
                'response' => '{"joined":true,"tournament_id":1}',
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/partner/wallet/debit',
                'auth' => 'Private API key (Bearer) + IP whitelist',
                'description' => 'POWERFUL: debit a player wallet. reference_id is idempotent — resending it returns the original entry instead of double-spending.',
                'params' => [
                    'user_id' => 'integer, required',
                    'amount_paise' => 'integer, required — positive',
                    'reference_id' => 'string, required — your idempotency key',
                    'reason' => 'string, optional — audit note',
                ],
                'response' => '{"entry_id":99,"new_balance_paise":4500}',
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/partner/wallet/credit',
                'auth' => 'Private API key (Bearer) + IP whitelist',
                'description' => 'POWERFUL: credit a player wallet. Same reference_id idempotency as debit. Keep private keys IP-whitelisted and never expose them in client-side code.',
                'params' => [
                    'user_id' => 'integer, required',
                    'amount_paise' => 'integer, required — positive',
                    'reference_id' => 'string, required — your idempotency key',
                    'reason' => 'string, optional — audit note',
                ],
                'response' => '{"entry_id":100,"new_balance_paise":14500}',
            ],
        ];
    }

    public static function curlExample(array $endpoint): string
    {
        $auth = str_contains($endpoint['auth'], 'Player token')
            ? '-H "Authorization: Bearer <PLAYER_TOKEN>"'
            : '-H "Authorization: Bearer <API_KEY>"';

        $data = '';
        if ($endpoint['method'] === 'POST' && $endpoint['params'] !== []) {
            $sample = [];
            foreach ($endpoint['params'] as $name => $desc) {
                $sample[$name] = str_contains($desc, 'integer') ? 500 : 'value';
            }
            $data = " \\\n  -H \"Content-Type: application/json\" \\\n  -d '".json_encode($sample)."'";
        }

        $path = preg_replace('/\{[^}]+\}/', '1', $endpoint['path']);

        return "curl -X {$endpoint['method']} https://YOUR_DOMAIN{$path} \\\n  {$auth}{$data}";
    }
}

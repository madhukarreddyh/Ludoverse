<?php

namespace Tests\Feature;

use App\Models\LudoMatch;
use App\Models\User;
use App\Services\Ludo\MatchService;
use App\Services\TableManager;
use App\Services\WalletService;

/**
 * Phase 7 — PWA frontend: manifest, service worker, offline page, lobby,
 * match board (seated / spectator / private), splash, layout metas, and
 * the bottom-nav pages.
 */
class PwaFrontendTest extends LicensedTestCase
{
    protected WalletService $wallets;

    protected MatchService $matches;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallets = app(WalletService::class);
        $this->matches = app(MatchService::class);
    }

    private function fundedUser(int $paise = 100000): User
    {
        $user = User::factory()->create();
        $this->wallets->credit($user, 'deposit', $paise, 'seed_' . $user->id . '_' . uniqid());

        return $user;
    }

    // ------------------------------------------------------------------
    // PWA shell files
    // ------------------------------------------------------------------

    public function test_manifest_json_is_valid_pwa(): void
    {
        $path = public_path('manifest.json');
        $this->assertFileExists($path);

        $manifest = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($manifest, 'manifest.json must be valid JSON');
        $this->assertSame('LudoVerse', $manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['background_color']);
        $this->assertNotEmpty($manifest['theme_color']);

        $sizes = [];
        foreach ($manifest['icons'] as $icon) {
            $sizes[] = $icon['sizes'];
            $this->assertFileExists(public_path($icon['src']), "icon {$icon['src']} missing");
        }
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $purposes = array_column($manifest['icons'], 'purpose');
        $this->assertContains('maskable', $purposes);
    }

    public function test_manifest_served_with_manifest_content_type(): void
    {
        $this->get('/manifest.json')
            ->assertOk()
            ->assertHeader('content-type', 'application/manifest+json');
    }

    public function test_service_worker_served_with_javascript_content_type(): void
    {
        $response = $this->get('/sw.js')->assertOk();
        $this->assertStringContainsString('javascript', $response->headers->get('content-type'));
        $response->assertSee('networkFirst', false);
        $response->assertSee('/offline.html', false);
    }

    public function test_offline_page_exists_and_is_served(): void
    {
        $this->assertFileExists(public_path('offline.html'));
        $this->get('/offline.html')
            ->assertOk()
            ->assertSee('offline', false);
    }

    public function test_splash_standalone_renders(): void
    {
        $this->get('/splash')
            ->assertOk()
            ->assertSee('LudoVerse', false);
    }

    // ------------------------------------------------------------------
    // Layout metas
    // ------------------------------------------------------------------

    public function test_layout_has_pwa_metas_and_helpers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('name="viewport"', false)
            ->assertSee('name="theme-color"', false)
            ->assertSee('rel="manifest"', false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee('/sw.js', false)
            ->assertSee('beforeinstallprompt', false)
            ->assertSee('install-app-btn', false)
            ->assertSee('toast-stack', false)
            ->assertSee('splash-overlay', false);
    }

    public function test_layout_shows_bottom_nav_for_authed_user(): void
    {
        $user = $this->fundedUser();
        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('aria-label="Primary"', false)
            ->assertSee(route('play.lobby'), false)
            ->assertSee(route('wallet.index'), false)
            ->assertSee(route('friends.page'), false)
            ->assertSee(route('profile'), false);
    }

    // ------------------------------------------------------------------
    // Lobby
    // ------------------------------------------------------------------

    public function test_lobby_requires_auth(): void
    {
        $this->get('/play')->assertRedirect('/login');
    }

    public function test_lobby_shows_allowed_tables_from_liquidity_ladder(): void
    {
        $user = $this->fundedUser();
        User::where('id', $user->id)->update(['last_seen_at' => now()]);

        $online = TableManager::onlineCount();
        $this->assertSame(1, $online);
        $bets = TableManager::allowedBets($online);
        $this->assertSame([500, 1000], $bets);

        $response = $this->actingAs($user)->get('/play')->assertOk();
        foreach (['1v1', '2v2', '3v3', '4v4'] as $mode) {
            $response->assertSee($mode, false);
        }
        // Open bet tables are rendered as rupee buttons.
        $response->assertSee('&#8377;5.00', false);
        $response->assertSee('&#8377;10.00', false);
        $response->assertSee((string) $online . ' online', false);
        $response->assertSee(route('tournaments.index'), false);
    }

    public function test_lobby_shows_resume_card_for_active_match(): void
    {
        $user = $this->fundedUser();
        $match = $this->matches->findOrCreateMatch($user, '1v1', 500);

        $this->actingAs($user)->get('/play')
            ->assertOk()
            ->assertSee('Resume your match', false)
            ->assertSee(route('play.match.board', $match), false);
    }

    // ------------------------------------------------------------------
    // Board
    // ------------------------------------------------------------------

    public function test_board_200_for_seated_player(): void
    {
        $user = $this->fundedUser();
        $match = $this->matches->findOrCreateMatch($user, '1v1', 500);

        $this->actingAs($user)->get("/play/match/{$match->id}/board")
            ->assertOk()
            ->assertSee('id="ludo-board"', false)
            ->assertSee('roll-btn', false)
            ->assertDontSee('Spectating', false);
    }

    public function test_board_200_spectator_for_public_match(): void
    {
        $player = $this->fundedUser();
        $watcher = $this->fundedUser();
        $match = $this->matches->findOrCreateMatch($player, '1v1', 500);
        $this->assertFalse($match->is_private);

        $this->actingAs($watcher)->get("/play/match/{$match->id}/board")
            ->assertOk()
            ->assertSee('id="ludo-board"', false)
            ->assertSee('Spectating', false)
            ->assertDontSee('id="roll-btn"', false);
    }

    public function test_board_403_for_private_match_non_invitee(): void
    {
        $inviter = $this->fundedUser();
        $friend = $this->fundedUser();
        $stranger = $this->fundedUser();

        $match = $this->matches->createPrivateMatch($inviter, $friend, 500);
        $this->assertTrue($match->is_private);

        $this->actingAs($stranger)->get("/play/match/{$match->id}/board")->assertForbidden();
    }

    public function test_board_200_for_private_match_invitee_as_spectator(): void
    {
        $inviter = $this->fundedUser();
        $friend = $this->fundedUser();

        $match = $this->matches->createPrivateMatch($inviter, $friend, 500);

        $this->actingAs($friend)->get("/play/match/{$match->id}/board")
            ->assertOk()
            ->assertSee('Spectating', false);
    }

    public function test_board_requires_auth(): void
    {
        $user = $this->fundedUser();
        $match = $this->matches->findOrCreateMatch($user, '1v1', 500);

        $this->get("/play/match/{$match->id}/board")->assertRedirect('/login');
    }

    // ------------------------------------------------------------------
    // Bottom-nav pages
    // ------------------------------------------------------------------

    public function test_profile_page_renders(): void
    {
        $user = $this->fundedUser();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee($user->username, false)
            ->assertSee($user->game_id, false);
    }

    public function test_profile_requires_auth(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_friends_page_renders(): void
    {
        $user = $this->fundedUser();

        $this->actingAs($user)->get('/friends/page')
            ->assertOk()
            ->assertSee('friend-game-id', false);
    }

    public function test_maintenance_503_page_still_renders(): void
    {
        $this->assertFileExists(resource_path('views/errors/503.blade.php'));
        $response = $this->get('/does-not-exist-at-all');
        $response->assertNotFound();
    }
}

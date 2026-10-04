<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;

/**
 * Friends: request by game_id, accept/reject, list with online status,
 * unfriend, and the friend-invite-to-match flow.
 */
class FriendTest extends LicensedTestCase
{
    public function test_game_id_is_assigned_on_signup(): void
    {
        $user = User::factory()->create();
        $this->assertMatchesRegularExpression('/^LV\d{6}$/', $user->fresh()->game_id);
    }

    public function test_game_id_is_unique(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->assertNotSame($a->fresh()->game_id, $b->fresh()->game_id);
    }

    public function test_request_accept_list_unfriend(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        // Request by game_id.
        $resp = $this->actingAs($alice)
            ->postJson('/friends/request', ['game_id' => $bob->fresh()->game_id])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');
        $friendshipId = $resp->json('friendship_id');
        $this->assertNotNull($friendshipId);

        $friendship = Friendship::findOrFail($friendshipId);
        $this->assertSame($alice->id, (int) $friendship->requester_id);
        $this->assertSame($bob->id, $friendship->otherUserId($alice->id));

        // Duplicate request is rejected.
        $this->actingAs($alice)
            ->postJson('/friends/request', ['game_id' => $bob->fresh()->game_id])
            ->assertStatus(422);

        // Can't friend yourself.
        $this->actingAs($alice)
            ->postJson('/friends/request', ['game_id' => $alice->fresh()->game_id])
            ->assertStatus(422);

        // Bob accepts.
        $this->actingAs($bob)
            ->postJson("/friends/{$friendshipId}/accept")
            ->assertOk()
            ->assertJsonPath('status', 'accepted');

        $this->assertTrue(Friendship::areFriends($alice->id, $bob->id));

        // Both sides list each other as friends.
        $aliceList = $this->actingAs($alice)->getJson('/friends')->assertOk()->json('friends');
        $this->assertCount(1, $aliceList);
        $this->assertSame($bob->id, $aliceList[0]['user_id']);
        $this->assertSame($bob->fresh()->game_id, $aliceList[0]['game_id']);

        $bobList = $this->actingAs($bob)->getJson('/friends')->assertOk()->json('friends');
        $this->assertCount(1, $bobList);
        $this->assertSame($alice->id, $bobList[0]['user_id']);

        // Unfriend.
        $this->actingAs($alice)
            ->deleteJson("/friends/{$friendshipId}")
            ->assertOk()
            ->assertJsonPath('removed', true);

        $this->assertFalse(Friendship::areFriends($alice->id, $bob->id));
        $this->assertCount(0, $this->actingAs($alice)->getJson('/friends')->json('friends'));
    }

    public function test_reject(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $resp = $this->actingAs($alice)->postJson('/friends/request', ['game_id' => $bob->fresh()->game_id]);
        $friendshipId = $resp->json('friendship_id');

        // Only the addressee can reject.
        $this->actingAs($alice)
            ->postJson("/friends/{$friendshipId}/reject")
            ->assertForbidden();

        $this->actingAs($bob)
            ->postJson("/friends/{$friendshipId}/reject")
            ->assertOk()
            ->assertJsonPath('status', 'rejected');

        $this->assertFalse(Friendship::areFriends($alice->id, $bob->id));
        // Rejected requests may be sent again.
        $this->actingAs($alice)
            ->postJson('/friends/request', ['game_id' => $bob->fresh()->game_id])
            ->assertCreated();
    }

    public function test_online_status_in_friends_list(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create(['last_seen_at' => now()]);
        $carol = User::factory()->create(['last_seen_at' => now()->subMinutes(10)]);

        foreach ([$bob, $carol] as $friend) {
            $resp = $this->actingAs($alice)
                ->postJson('/friends/request', ['game_id' => $friend->fresh()->game_id]);
            $this->actingAs($friend)
                ->postJson("/friends/{$resp->json('friendship_id')}/accept");
        }
        // Accepting touched Carol's last_seen_at via middleware; she is
        // meant to be the offline friend here.
        $carol->forceFill(['last_seen_at' => now()->subMinutes(10)])->save();

        $list = $this->actingAs($alice)->getJson('/friends')->assertOk()->json('friends');
        $byId = collect($list)->keyBy('user_id');

        $this->assertTrue($byId[$bob->id]['online']);
        $this->assertFalse($byId[$carol->id]['online']);
    }

    public function test_last_seen_updates_on_authenticated_requests(): void
    {
        $user = User::factory()->create(['last_seen_at' => now()->subHour()]);
        $this->assertFalse($user->fresh()->isOnline());

        $this->actingAs($user)->getJson('/friends')->assertOk();

        $this->assertTrue($user->fresh()->isOnline());
    }

    public function test_friend_invite_creates_private_match(): void
    {
        $wallets = app(\App\Services\WalletService::class);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $wallets->credit($alice, 'deposit', 100000, 'seed_'.$alice->id);
        $wallets->credit($bob, 'deposit', 100000, 'seed_'.$bob->id);

        // Become friends first.
        $resp = $this->actingAs($alice)->postJson('/friends/request', ['game_id' => $bob->fresh()->game_id]);
        $this->actingAs($bob)->postJson("/friends/{$resp->json('friendship_id')}/accept");

        // Alice invites Bob to a private 1v1.
        $resp = $this->actingAs($alice)
            ->postJson('/play/find', ['mode' => '1v1', 'bet_paise' => 500, 'invite_game_id' => $bob->fresh()->game_id])
            ->assertCreated();

        $matchId = $resp->json('match_id');
        $this->assertNotNull($matchId);

        // A stranger cannot take someone else's private invite.
        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->postJson("/play/match/{$matchId}/join-invite")
            ->assertForbidden();

        // Bob joins via the invite route.
        $this->actingAs($bob)
            ->postJson("/play/match/{$matchId}/join-invite")
            ->assertOk()
            ->assertJsonPath('status', 'running');
    }
}

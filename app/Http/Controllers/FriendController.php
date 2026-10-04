<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Friends: request by game_id, accept/reject, list (with online
 * status), remove. All JSON (the client renders the list).
 */
class FriendController extends Controller
{
    /**
     * Accepted friends with online presence.
     */
    public function index(Request $request)
    {
        $me = $request->user()->id;

        $friendships = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('requester_id', $me)->orWhere('addressee_id', $me))
            ->with(['requester', 'addressee'])
            ->orderByDesc('updated_at')
            ->get();

        $friends = $friendships->map(function (Friendship $f) use ($me) {
            $other = (int) $f->requester_id === $me ? $f->addressee : $f->requester;

            return [
                'friendship_id' => $f->id,
                'user_id' => $other->id,
                'name' => $other->name,
                'game_id' => $other->game_id,
                'online' => $other->isOnline(),
                'last_seen_at' => $other->last_seen_at?->toIso8601String(),
            ];
        });

        return response()->json(['friends' => $friends]);
    }

    /**
     * Send a friend request to a game_id.
     */
    public function request(Request $request)
    {
        $data = $request->validate([
            'game_id' => ['required', 'string', 'max:16'],
        ]);

        $target = User::where('game_id', $data['game_id'])->first();
        if (! $target) {
            return response()->json([
                'error' => ['code' => 'USER_NOT_FOUND', 'message' => 'No player with that game ID.'],
            ], 404);
        }

        $me = $request->user()->id;
        if ((int) $target->id === (int) $me) {
            return response()->json([
                'error' => ['code' => 'CANNOT_FRIEND_SELF', 'message' => 'You cannot friend yourself.'],
            ], 422);
        }

        $existing = Friendship::between($me, (int) $target->id);
        if ($existing && $existing->status !== 'rejected') {
            return response()->json([
                'error' => ['code' => 'ALREADY_EXISTS', 'message' => "A friendship already exists (status: {$existing->status})."],
            ], 422);
        }
        // A rejected request may be sent again — replace the old row.
        $existing?->delete();

        $friendship = Friendship::create([
            'requester_id' => $me,
            'addressee_id' => $target->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'friendship_id' => $friendship->id,
            'status' => 'pending',
        ], 201);
    }

    /**
     * Accept a pending request (addressee only).
     */
    public function accept(Request $request, Friendship $friendship)
    {
        return $this->respond($request, $friendship, 'accepted');
    }

    /**
     * Reject a pending request (addressee only).
     */
    public function reject(Request $request, Friendship $friendship)
    {
        return $this->respond($request, $friendship, 'rejected');
    }

    protected function respond(Request $request, Friendship $friendship, string $status)
    {
        if ((int) $friendship->addressee_id !== (int) $request->user()->id) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Only the recipient can respond.'],
            ], 403);
        }
        if ($friendship->status !== 'pending') {
            return response()->json([
                'error' => ['code' => 'NOT_PENDING', 'message' => 'This request is no longer pending.'],
            ], 422);
        }

        $friendship->update(['status' => $status]);

        return response()->json(['friendship_id' => $friendship->id, 'status' => $status]);
    }

    /**
     * Remove a friend (or cancel/withdraw a request) — either party.
     */
    public function destroy(Request $request, Friendship $friendship)
    {
        $me = (int) $request->user()->id;
        if ((int) $friendship->requester_id !== $me && (int) $friendship->addressee_id !== $me) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Not your friendship.'],
            ], 403);
        }

        $friendship->delete();

        return response()->json(['removed' => true]);
    }
}

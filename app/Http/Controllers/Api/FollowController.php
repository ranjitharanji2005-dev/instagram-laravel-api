<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function toggle(Request $request, User $user)
    {
        $currentUser = $request->user();

        if ($currentUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot follow yourself.',
            ], 422);
        }

        $follow = Follow::where('follower_id', $currentUser->id)
            ->where('following_id', $user->id)
            ->first();

        if ($follow) {
            $follow->delete();

            return response()->json([
                'success' => true,
                'following' => false,
                'message' => 'Unfollowed successfully.',
            ]);
        }

        Follow::create([
            'follower_id' => $currentUser->id,
            'following_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'following' => true,
            'message' => 'Followed successfully.',
        ], 201);
    }

    public function status(Request $request, User $user)
    {
        $following = Follow::where('follower_id', $request->user()->id)
            ->where('following_id', $user->id)
            ->exists();

        return response()->json([
            'following' => $following,
        ]);
    }

    public function followers(User $user)
    {
        $followers = Follow::where('following_id', $user->id)
            ->with('follower:id,name')
            ->get()
            ->pluck('follower')
            ->filter()
            ->values();

        return response()->json($followers);
    }

    public function following(User $user)
    {
        $following = Follow::where('follower_id', $user->id)
            ->with('following:id,name')
            ->get()
            ->pluck('following')
            ->filter()
            ->values();

        return response()->json($following);
    }
}

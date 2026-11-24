<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Follow;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                'profile_image' => $user->profile_image,
                'profile_image_url' => $user->profile_image
                    ? asset('storage/' . $user->profile_image)
                    : null,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'bio' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $user->bio = $validated['bio'] ?? null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile bio updated successfully.',
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                'profile_image' => $user->profile_image,
                'profile_image_url' => $user->profile_image
                    ? asset('storage/' . $user->profile_image)
                    : null,
            ],
        ]);
    }

    public function showUser(Request $request, User $user)
    {
        $currentUser = $request->user();

        $isFollowing = Follow::where('follower_id', $currentUser->id)
            ->where('following_id', $user->id)
            ->exists();

        return response()->json([
            'success' => true,
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'bio' => $user->bio,
                'profile_image' => $user->profile_image,
                'profile_image_url' => $user->profile_image
                    ? asset('storage/' . $user->profile_image)
                    : null,

                // Total posts uploaded by this user.
                'posts_count' => $user->posts()->count(),

                // Total followers of this user.
                'followers_count' => Follow::where(
                    'following_id',
                    $user->id
                )->count(),

                // Total accounts this user follows.
                'following_count' => Follow::where(
                    'follower_id',
                    $user->id
                )->count(),

                // Whether the logged-in user follows this user.
                'is_following' => $isFollowing,
            ],
        ]);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'profile_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $user = $request->user();

        // Delete the previous image, if one exists.
        if ($user->profile_image) {
            \Illuminate\Support\Facades\Storage::disk('public')
                ->delete($user->profile_image);
        }

        // Save the new image in storage/app/public/profile_images.
        $path = $request->file('profile_image')
            ->store('profile_images', 'public');

        $user->profile_image = $path;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile image uploaded successfully.',
            'profile_image' => $user->profile_image,
            'profile_image_url' => asset('storage/' . $user->profile_image),
        ]);
    }
}

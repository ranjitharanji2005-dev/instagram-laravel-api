<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
      public function index(Request $request)
    {
        $user = $request->user();

        // Get IDs of users you follow, including yourself.
        $followingIds = $user->following()
            ->pluck('users.id')
            ->push($user->id)
            ->unique();

        // Get active stories from followed users and yourself.
        $stories = Story::with('user:id,name,profile_image')
            ->whereIn('user_id', $followingIds)
            ->where('expires_at', '>', now())
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy('user_id')
            
            ->map(function ($userStories) {
                $storyUser = $userStories->first()->user;

                if ($storyUser && $storyUser->profile_image) {
                    $storyUser->profile_image_url = asset(
                        'storage/' . $storyUser->profile_image
                    );
                } else if ($storyUser) {
                    $storyUser->profile_image_url = null;
                }

                return [
                    'user' => $storyUser,
                    'stories' => $userStories->values(),
                ];
            })

            ->values();


        return response()->json([
            'success' => true,
            'stories' => $stories,
        ]);
    }

    // Upload a new Story
    public function store(Request $request)
    {
        // 1. Validate the uploaded media
        $request->validate([
            'media' => ['required','file','mimes:jpg,jpeg,png,webp,mp4,mov','max:20480',
            ],
        ]);

        // 2. Get the uploaded file
        $file = $request->file('media');

        // 3. Save the file in storage/app/public/stories
        $path = $file->store('stories', 'public');

        // 4. Identify whether the media is a photo or video
        $mediaType = str_starts_with(
            $file->getMimeType(),
            'video/'
        ) ? 'video' : 'image';

        // 5. Create the Story record in the database
        $story = Story::create([
            'user_id' => $request->user()->id,
            'media_path' => $path,
            'media_type' => $mediaType,
            'expires_at' => now()->addHours(24),
        ]);

        // 6. Return a JSON response
        return response()->json([
            'success' => true,
            'message' => 'Story uploaded successfully.',
            'story' => $story->load('user:id,name'),
        ], 201);
    }

    
    // 3. Delete your own Story
    public function destroy(Request $request, Story $story)
    {
        // Only the Story's owner can delete it
        if ($story->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to delete this Story.',
            ], 403);
        }

        // Delete the media file
        Storage::disk('public')->delete($story->media_path);

        // Delete the database record
        $story->delete();

        return response()->json([
            'success' => true,
            'message' => 'Story deleted successfully.',
        ]);
    }
}

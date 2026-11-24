<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;


class PostController extends Controller
{
   public function index(Request $request): JsonResponse
    {   
        $user = $request->user();

        // Get IDs of users you follow, including yourself.
        $followingIds = $user->following()
            ->pluck('users.id')
            ->push($user->id)
            ->unique();

        // Display posts from followed users and your own posts.
        $posts = Post::with('user', 'comments.user:id,name')
            ->withCount('likes')
            ->withExists([
                'likes as liked' => function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                },
            ])
            ->whereIn('user_id', $followingIds)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'posts' => $posts,
        ]);
    }

    public function userPosts(User $user): JsonResponse
    {
        $posts = Post::with('user', 'comments.user:id,name')
            ->withCount('likes')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'posts' => $posts,
        ]);
    }

    
    public function store(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'caption' => ['nullable', 'string', 'max:2200'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $path = $request->file('image')->store('posts', 'public');

        $post = \App\Models\Post::create([
            'user_id' => $request->user()->id,
            'caption' => $request->input('caption'),
            'image' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully.',
            'post' => $post,
        ], 201);
    }

        /**
     * Update a post caption.
     * Only the post owner can edit it.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        // Check whether the authenticated user owns this post.
        if ((int) $post->user_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to edit this post.',
            ], 403);
        }

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:2200'],
        ]);

        $post->caption = $validated['caption'] ?? null;
        $post->save();

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully.',
            'post' => $post,
        ]);
    }

    /**
     * Delete a post.
     * Only the post owner can delete it.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        // Check whether the authenticated user owns this post.
        if ((int) $post->user_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this post.',
            ], 403);
        }

        $imagePath = $post->image;

        // Delete the database record.
        $post->delete();

        // Delete its image from public storage, if present.
        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully.',
        ]);
    }
    

}

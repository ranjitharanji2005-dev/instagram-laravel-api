<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Like;
use App\Models\Post;

class LikeController extends Controller
{
    public function toggle(Request $request, Post $post)
    {
        $user = $request->user();

        $like = Like::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        if ($like) {
            $like->delete();

            return response()->json([
                'success' => true,
                'liked' => false,
                'message' => 'Post unliked successfully.',
            ]);
        }

        Like::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        return response()->json([
            'success' => true,
            'liked' => true,
            'message' => 'Post liked successfully.',
        ]);
    }
}

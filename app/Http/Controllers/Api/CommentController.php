<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\Post;

class CommentController extends Controller
{
    public function store(Request $request, Post $post)
    {
        $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'post_id' => $post->id,
            'comment' => $request->input('comment'),
        ]);

        $comment->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully.',
            'comment' => $comment,
        ], 201);
    }

    public function destroy(Request $request, Comment $comment)
    {
        // Allow users to delete only their own comments.
        if ($comment->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own comments.',
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully.',
        ]);
    }

    public function update(Request $request, Comment $comment)
    {
        // Only the comment's author can edit it.
        if ((int) $comment->user_id !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own comments.',
            ], 403);
        }

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $comment->update([
            'comment' => $validated['comment'],
        ]);

        $comment->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Comment updated successfully.',
            'comment' => $comment,
        ]);
    }
}

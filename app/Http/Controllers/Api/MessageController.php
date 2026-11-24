<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    // Get conversation messages with a user you follow.
    public function index(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        $isFollowing = $currentUser->following()
            ->where('users.id', $user->id)
            ->exists();

        if (!$isFollowing) {
            return response()->json([
                'message' => 'You can chat only with users you follow.',
            ], 403);
        }

        $messages = Message::where(function ($query) use ($currentUser, $user) {
            $query->where('sender_id', $currentUser->id)
                ->where('receiver_id', $user->id);
        })
        ->orWhere(function ($query) use ($currentUser, $user) {
            $query->where('sender_id', $user->id)
                ->where('receiver_id', $currentUser->id);
        })
        ->orderBy('created_at')
        ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    // Send a message to a user you follow.
    public function store(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        $isFollowing = $currentUser->following()
            ->where('users.id', $user->id)
            ->exists();

        if (!$isFollowing) {
            return response()->json([
                'message' => 'You can chat only with users you follow.',
            ], 403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = Message::create([
            'sender_id' => $currentUser->id,
            'receiver_id' => $user->id,
            'message' => $validated['message'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
        ], 201);
    }

    public function conversations(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $users = $currentUser->following()
            ->select('users.id', 'users.name','users.profile_image')
            ->get();

        $conversations = $users->map(function ($user) use ($currentUser) {
            $latestMessage = Message::where(function ($query) use ($currentUser, $user) {
                $query->where('sender_id', $currentUser->id)
                    ->where('receiver_id', $user->id);
            })
            ->orWhere(function ($query) use ($currentUser, $user) {
                $query->where('sender_id', $user->id)
                    ->where('receiver_id', $currentUser->id);
            })
            ->latest()
            ->first();

            $unreadCount = Message::where('sender_id', $user->id)
                ->where('receiver_id', $currentUser->id)
                ->where('is_read', false)
                ->count();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'profile_image' => $user->profile_image,

                'profile_image_url' => $user->profile_image
                    ? asset('storage/' . $user->profile_image)
                    : null,
                    
                'latest_message' => $latestMessage?->message,
                'latest_message_at' => $latestMessage?->created_at,
                'unread_count' => $unreadCount,
            ];
        });

        $conversations = $conversations
            ->sortByDesc('latest_message_at')
            ->values();

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }

    public function markAsRead(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        // Check whether the logged-in user follows this user.
        $isFollowing = $currentUser->following()
            ->where('users.id', $user->id)
            ->exists();

        if (!$isFollowing) {
            return response()->json([
                'message' => 'You can chat only with users you follow.',
            ], 403);
        }

        // Mark incoming unread messages as read.
        Message::where('sender_id', $user->id)
            ->where('receiver_id', $currentUser->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read.',
        ]);
    }
}

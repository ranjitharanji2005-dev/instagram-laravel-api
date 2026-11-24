<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;


class UserController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $search = $request->query('q');

        $users = User::query()
            ->where('name', 'like', '%' . $search . '%')
            ->where('id', '!=', $request->user()->id)
            ->select('id', 'name', 'email', 'profile_image')
            ->limit(20)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile_image' => $user->profile_image,
                    'profile_image_url' => $user->profile_image
                        ? url('storage/' . $user->profile_image)
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'users' => $users,
        ]);
    }
}

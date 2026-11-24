<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\GoogleAuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Instagram Laravel API is working!',
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',[AuthController::class,'login']);
Route::get('/auth/google/redirect', [GoogleAuthController::class,'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class,'handleGoogleCallback']);
Route::post('/auth/google/exchange', [GoogleAuthController::class,'exchangeCode'])->middleware('throttle:10,1');
Route::post('/auth/google/verify-email-otp', [GoogleAuthController::class,'verifyEmailOtp'])->middleware('throttle:10,1');
Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);
Route::middleware('auth:sanctum')->get('/posts', [PostController::class, 'index']);
Route::middleware('auth:sanctum')->post('/posts', [PostController::class, 'store']);
Route::middleware('auth:sanctum')->get('/users/{user}/posts',[PostController::class, 'userPosts']);
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);
});
Route::middleware('auth:sanctum')->post('/posts/{post}/like',[LikeController::class, 'toggle']);
Route::middleware('auth:sanctum')->post('/posts/{post}/comments',[CommentController::class, 'store']);
Route::middleware('auth:sanctum')->delete('/comments/{comment}',[CommentController::class, 'destroy']);
Route::middleware('auth:sanctum')->put('/comments/{comment}',[CommentController::class, 'update']);
Route::middleware('auth:sanctum')->post('/stories', [StoryController::class, 'store']);
Route::middleware('auth:sanctum')->get('/stories', [StoryController::class, 'index']);
Route::middleware('auth:sanctum')->delete('/stories/{story}', [StoryController::class, 'destroy']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/users/{user}/follow', [FollowController::class, 'toggle']);
    Route::get('/users/{user}/follow-status', [FollowController::class,'status',
]);
});

Route::get('/users/{user}/followers', [FollowController::class, 'followers']);
Route::get('/users/{user}/following', [FollowController::class, 'following']);
Route::middleware('auth:sanctum')->get('/users/{user}/profile', [ProfileController::class,'showUser',]);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/image', [ProfileController::class, 'uploadImage']);
});
Route::middleware('auth:sanctum')->get('/users/search', [UserController::class, 'search']);
Route::middleware('auth:sanctum')->get('/following-users', function (Request $request) {
    $users = $request->user()
        ->following()
        ->select('users.id', 'users.name', 'users.email')
        ->get();

    return response()->json([
        'success' => true,
        'users' => $users,
    ]);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{user}/messages', [MessageController::class,'index']);
    Route::post('/users/{user}/messages', [MessageController::class,'store']);
});
Route::middleware('auth:sanctum')->get('/conversations',[MessageController::class, 'conversations']);
Route::middleware('auth:sanctum')->patch('/users/{user}/messages/read',[MessageController::class, 'markAsRead']);
Route::middleware('auth:sanctum')->post('/2fa/setup', [TwoFactorController::class,'setup',]);
Route::middleware('auth:sanctum')->post('/2fa/verify-setup', [TwoFactorController::class,'verifySetup',]);
Route::post('/2fa/verify-login', [AuthController::class,'verifyTwoFactorLogin'])->middleware('throttle:10,1');





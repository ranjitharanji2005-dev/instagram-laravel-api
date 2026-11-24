<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1. Validate the registration details
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // 2. Return validation errors if any field is invalid
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 3. Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 4. Return a successful JSON response
        return response()->json([
            'success' => true,
            'message' => 'Registration successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

     
    public function login(Request $request)
    {
        
    // Step 1: Validate the login details.
        $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|string',
        ]);

    // Step 2: Return validation errors if details are missing.
        if ($validator->fails()) {
            return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
            ], 422);
        }

    // Step 3: Find the user by email.
        $user = User::where('email', $request->email)->first();
        
    // Step 4: Check the password.
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
            'success' => false,
            'message' => 'Invalid email or password.',
            ], 401);
        }

    
        // Step 5: Require authenticator verification when 2FA is enabled.
        if ($user->two_factor_enabled) {
            // Generate a random, temporary challenge ID.
            $challengeId = bin2hex(random_bytes(32));

            $cacheKey = 'two_factor_login:' . hash('sha256', $challengeId);

            // Store the challenge for five minutes.
            Cache::put($cacheKey, [
                'user_id' => $user->id,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ], now()->addMinutes(5));

            // Do not issue a normal login token yet.
            return response()->json([
                'success' => true,
                'two_factor_required' => true,
                'challenge_id' => $challengeId,
                'message' => 'Enter your authenticator code to complete login.',
            ], 200);
        }

        // Users without 2FA continue using the existing login flow.
        $token = $user->createToken('instagram-nextjs')->plainTextToken;
    // Step 6: Return the user details and token.
        return response()->json([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            ],
            'token' => $token,
        ], 200);
    }
    
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }

    
    public function verifyTwoFactorLogin(Request $request)
    {
        // Step 1: Validate the challenge ID and authenticator code.
        $validator = Validator::make($request->all(), [
            'challenge_id' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-f0-9]{64}$/',
            ],
            'code' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Step 2: Find the temporary login challenge.
        $challengeId = $request->input('challenge_id');

        $cacheKey = 'two_factor_login:' . hash('sha256', $challengeId);

        $challenge = Cache::get($cacheKey);

        if (
            !$challenge ||
            !isset(
                $challenge['user_id'],
                $challenge['attempts'],
                $challenge['expires_at']
            ) ||
            $challenge['expires_at'] < now()->timestamp
        ) {
            Cache::forget($cacheKey);

            return response()->json([
                'success' => false,
                'message' => 'Your login challenge expired. Please log in again.',
            ], 401);
        }

        // Step 3: Limit incorrect code attempts.
        if ($challenge['attempts'] >= 5) {
            Cache::forget($cacheKey);

            return response()->json([
                'success' => false,
                'message' => 'Too many invalid codes. Please log in again.',
            ], 429);
        }

        // Step 4: Find the user and check that 2FA is still enabled.
        $user = User::find($challenge['user_id']);

        if (!$user || !$user->two_factor_enabled || !$user->two_factor_secret) {
            Cache::forget($cacheKey);

            return response()->json([
                'success' => false,
                'message' => 'Your login challenge is no longer valid. Please log in again.',
            ], 401);
        }

        // Step 5: Verify the code from the authenticator app.
        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey(
            $user->two_factor_secret,
            $request->input('code')
        );

        if (!$valid) {
            $challenge['attempts']++;

            if ($challenge['attempts'] >= 5) {
                Cache::forget($cacheKey);

                return response()->json([
                    'success' => false,
                    'message' => 'Too many invalid codes. Please log in again.',
                ], 429);
            }

            $remainingSeconds = max(
                1,
                $challenge['expires_at'] - now()->timestamp
            );

            Cache::put($cacheKey, $challenge, $remainingSeconds);

            return response()->json([
                'success' => false,
                'message' => 'Invalid authenticator code. Please try again.',
            ], 422);
        }

        // Step 6: The code is correct. Remove the temporary challenge.
        Cache::forget($cacheKey);

        // Step 7: Only now create the actual login token.
        $token = $user->createToken('instagram-nextjs')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ], 200);
    }

}

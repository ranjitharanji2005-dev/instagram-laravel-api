<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GoogleLoginOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
        {
        $googleConfig = config('services.google');

        if (
            empty($googleConfig['client_id']) ||
            empty($googleConfig['client_secret']) ||
            empty($googleConfig['redirect'])
        ) {
            report(new \RuntimeException(
                'Google OAuth configuration is incomplete.'
            ));

            return response()->json([
                'success' => false,
                'message' => 'Google login is not configured correctly.',
            ], 500);
        }

        try {
            return Socialite::driver('google')
                ->stateless()
                ->redirect();
                
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to start Google login.',
            ], 500);
        }
    
    }

    public function handleGoogleCallback()
    {
        $challengeCacheKey = null;
        $frontendUrl = rtrim(
            config('services.google.frontend_url', 'http://localhost:3000'),
            '/'
        );

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            $googleId = $googleUser->getId();
            $email = $googleUser->getEmail();
            $googleData = $googleUser->getRaw();

            if (
                !$googleId ||
                !$email ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                !filter_var(
                    $googleData['email_verified'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                )
            ) {
                return redirect(
                    $frontendUrl . '/login?error=google_email_not_verified'
                );
            }

            // Find an existing account by Google ID.
            $user = User::where('google_id', $googleId)->first();

            if (!$user) {
                // Link an existing account with the same verified email.
                $user = User::where('email', $email)->first();

                if ($user) {
                    $user->google_id = $googleId;
                    $user->save();
                } else {
                    // Create a new account.
                    $user = User::create([
                        'name' => $googleUser->getName()
                            ?: $googleUser->getNickname()
                            ?: 'Google User',
                        'email' => $email,
                        'password' => Hash::make(Str::random(40)),
                        'google_id' => $googleId,
                    ]);

                    $user->forceFill([
                        'email_verified_at' => now(),
                    ])->save();
                }
            }

            // Generate a six-digit email OTP.
            $otp = (string) random_int(100000, 999999);
            $challengeId = bin2hex(random_bytes(32));

            $challengeCacheKey =
                'google_email_otp:' . hash('sha256', $challengeId);

            $expiresAt = now()->addMinutes(5);

            Cache::put($challengeCacheKey, [
                'user_id' => $user->id,
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => $expiresAt->timestamp,
            ], $expiresAt);

            // Queue the OTP email.
            try {
                Mail::to($user->email)->queue(
                    new GoogleLoginOtpMail($otp)
                );
            } catch (Throwable $e) {
                Cache::forget($challengeCacheKey);
                throw $e;
            }

            // Generate a short-lived, one-time exchange code.
            $exchangeCode = bin2hex(random_bytes(32));

            Cache::put(
                'google_oauth_exchange:' . hash('sha256', $exchangeCode),
                [
                    'challenge_id' => $challengeId,
                    'email' => $user->email,
                ],
                now()->addMinute()
            );

            return redirect(
                $frontendUrl . '/auth/google/callback?code='
                . urlencode($exchangeCode)
            );
        } catch (Throwable $e) {
            if ($challengeCacheKey) {
                Cache::forget($challengeCacheKey);
            }

            report($e);

            return redirect(
                $frontendUrl . '/login?error=google_login_failed'
            );
        }
    }

    public function exchangeCode(Request $request)
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-f0-9]{64}$/',
            ],
        ]);

        $throttleKey = 'google-exchange:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
            ], 429);
        }

        RateLimiter::hit($throttleKey, 60);

        $cacheKey = 'google_oauth_exchange:'
            . hash('sha256', $validated['code']);

        // Consume the code so it cannot be reused.
        $payload = Cache::pull($cacheKey);

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This Google login code has expired or already been used.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'email_otp_required' => true,
            'challenge_id' => $payload['challenge_id'],
            'email' => $this->maskEmail($payload['email']),
            'message' => 'Enter the verification code sent to your email.',
        ]);
    }

    public function verifyEmailOtp(Request $request)
    {
        $validated = $request->validate([
            'challenge_id' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-f0-9]{64}$/',
            ],
            'otp' => [
                'required',
                'string',
                'regex:/^[0-9]{6}$/',
            ],
        ]);

        $challengeHash = hash(
            'sha256',
            $validated['challenge_id']
        );

        $cacheKey = 'google_email_otp:' . $challengeHash;
        $throttleKey = 'google-otp-verify:' . $challengeHash;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            Cache::forget($cacheKey);

            return response()->json([
                'success' => false,
                'message' =>
                    'Too many attempts. Please sign in with Google again.',
            ], 429);
        }

        $challenge = Cache::get($cacheKey);

        if (
            !$challenge ||
            now()->timestamp >= $challenge['expires_at']
        ) {
            Cache::forget($cacheKey);
            RateLimiter::clear($throttleKey);

            return response()->json([
                'success' => false,
                'message' =>
                    'Your verification code has expired. Please sign in again.',
            ], 401);
        }

        if (!Hash::check($validated['otp'], $challenge['otp_hash'])) {
            RateLimiter::hit($throttleKey, 300);

            $challenge['attempts']++;

            if ($challenge['attempts'] >= 5) {
                Cache::forget($cacheKey);

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Too many incorrect attempts. Please sign in again.',
                ], 429);
            }

            $remainingSeconds =
                $challenge['expires_at'] - now()->timestamp;

            Cache::put(
                $cacheKey,
                $challenge,
                now()->addSeconds(max(1, $remainingSeconds))
            );

            return response()->json([
                'success' => false,
                'message' => 'Incorrect verification code. Please try again.',
                'attempts_remaining' => 5 - $challenge['attempts'],
            ], 422);
        }

        // Consume the OTP before creating the token.
        Cache::forget($cacheKey);
        RateLimiter::clear($throttleKey);

        $user = User::find($challenge['user_id']);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to find your account. Please sign in again.',
            ], 401);
        }

        $token = $user->createToken('instagram-nextjs')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Google login successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ]);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);

        return substr($name, 0, 1)
            . str_repeat('*', max(2, strlen($name) - 1))
            . '@' . $domain;
    }
}

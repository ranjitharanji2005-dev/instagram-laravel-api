<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function setup(Request $request)
    {
        $user = $request->user();

        // Do not generate a new secret if 2FA is already enabled.
        if ($user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is already enabled.',
            ], 409);
        }

        // Generate a secret only if one does not already exist.
        if (!$user->two_factor_secret) {
            $google2fa = new Google2FA();

            $user->two_factor_secret = $google2fa->generateSecretKey();
            $user->save();
        }

        $google2fa = new Google2FA();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'Instagram Clone'),
            $user->email,
            $user->two_factor_secret
        );

        return response()->json([
            'success' => true,
            'message' => 'Scan the QR code with your authenticator app, then verify a code to enable 2FA.',
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    public function verifySetup(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json([
                'success' => false,
                'message' => 'Please start 2FA setup first.',
            ], 400);
        }

        if ($user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Two-factor authentication is already enabled.',
            ], 409);
        }

        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        $valid = $google2fa->verifyKey(
            $user->two_factor_secret,
            $request->input('code')
        );

        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid authenticator code. Please try again.',
            ], 422);
        }

        $user->two_factor_enabled = true;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication enabled successfully.',
        ]);
    }
}

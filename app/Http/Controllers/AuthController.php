<?php

namespace App\Http\Controllers;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->stateless()
            ->scopes([
                'openid',
                'profile',
                'email',
                'https://www.googleapis.com/auth/calendar',
            ])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $existingUser = User::where('email', $googleUser->getEmail())->first();

        $user = User::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'google_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken ?? $existingUser?->google_refresh_token,
                'password' => bcrypt('google-login'),
            ]
        );

        $token = $user->createToken('auth_token')->plainTextToken;

        return redirect("http://localhost:5173/oauth-success?token=$token");
    }
}

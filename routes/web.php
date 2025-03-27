<?php

use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;

// Google OAuth redirect
Route::get('/auth/redirect', function () {
    return Socialite::driver('google')->redirect();
});

// Google OAuth callback
Route::get('/auth/callback', function () {
    $googleUser = Socialite::driver('google')->stateless()->user();

    $email = $googleUser->getEmail();
    $name = $googleUser->getName() ?? 'Unknown User';
    $originalAvatar = $googleUser->getAvatar() ?? '';
    $domain = Str::after($email, '@');

    $isParagonEmail = Str::endsWith($domain, 'paragoniu.edu.kh');
    $isWhitelisted = DB::table('whitelisted_emails')->where('email', $email)->exists();

    if (!$isParagonEmail && !$isWhitelisted) {
        abort(403, 'Not authorized');
    }

    // Create or find user
    $dummyPassword = bcrypt(Str::random(16));

    $user = User::firstOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password' => $dummyPassword,
        ]
    );

    $token = $user->createToken('auth_token')->plainTextToken;

    // Proxy the avatar through Laravel
    $proxiedAvatar = $originalAvatar
        ? url('/proxy-avatar?url=' . urlencode($originalAvatar))
        : '';

    // Redirect to frontend with data
    return Redirect::to(
        'http://localhost:3000/auth/callback?' .
        http_build_query([
            'token' => $token,
            'name' => $name,
            'email' => $email,
            'avatar' => $proxiedAvatar,
        ])
    );
});

// Proxy route for avatar
Route::get('/proxy-avatar', function () {
    $url = request('url');

    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        abort(400, 'Invalid image URL');
    }

    try {
        $imageResponse = Http::get($url);

        $contentType = $imageResponse->header('Content-Type') ?? 'image/jpeg';

        return Response::make($imageResponse->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'max-age=86400');
    } catch (\Exception $e) {
        abort(500, 'Failed to load avatar');
    }
});

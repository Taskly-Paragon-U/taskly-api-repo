<?php

use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redirect;

Route::get('/auth/redirect', function () {
    return Socialite::driver('google')->redirect();
});

Route::get('/auth/callback', function () {
    $googleUser = Socialite::driver('google')->stateless()->user();
    $email = $googleUser->getEmail();
    $name = $googleUser->getName() ?? 'Unknown User';
    $domain = Str::after($email, '@');

    $isParagonEmail = Str::endsWith($domain, 'paragoniu.edu.kh');
    $isWhitelisted = DB::table('whitelisted_emails')->where('email', $email)->exists();

    if (!$isParagonEmail && !$isWhitelisted) {
        abort(403, 'Not authorized');
    }

    // Hash a dummy password (not used for Google login)
    $dummyPassword = bcrypt(Str::random(16));

    $user = User::firstOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password' => $dummyPassword,
        ]
    );

    $token = $user->createToken('auth_token')->plainTextToken;

    return Redirect::to("http://localhost:3000/auth/callback?token=$token");
});


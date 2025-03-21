<?php

use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Redirect;

Route::get('/auth/redirect', function () {
    return Socialite::driver('google')->redirect();
});

Route::get('/auth/callback', function () {
    $googleUser = Socialite::driver('google')->stateless()->user();
    $email = $googleUser->getEmail();

    // Whitelist check (assume you have a table `whitelisted_emails`)
    $allowed = DB::table('whitelisted_emails')->where('email', $email)->exists();
    if (!$allowed) {
        abort(403, 'Not authorized');
    }

    // Create user if not exists
    $user = User::firstOrCreate(['email' => $email]);

    // Generate Sanctum token
    $token = $user->createToken('auth_token')->plainTextToken;

    // Redirect to frontend with token
    return Redirect::to("http://your-frontend.com/auth/callback?token=$token");
});

// Route::get('/', function () {
//     return view('welcome');
// });


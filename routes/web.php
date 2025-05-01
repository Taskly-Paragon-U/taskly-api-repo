<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\Contract;
use App\Models\Invite;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1) Kick off Google OAuth, capturing the desired post-login redirect
Route::get('/auth/redirect', function (Request $request) {
    // e.g. /auth/redirect?redirect=/invites/… or /dashboard
    $redirect = $request->query('redirect', '/dashboard');
    // stash it in session so it survives the OAuth round-trip
    $request->session()->put('login_redirect', $redirect);

    return Socialite::driver('google')
                    ->redirect();
});

// 2) Handle Google callback, issue Sanctum token, then return to Next.js
Route::get('/auth/callback', function (Request $request) {
    // pull out where we should send them after login
    $redirect = $request->session()->pull('login_redirect', '/dashboard');

    // fetch the Google user (stateless if you prefer)
    $googleUser = Socialite::driver('google')
                           ->stateless()
                           ->user();

    $email  = $googleUser->getEmail();
    $name   = $googleUser->getName()  ?? 'Unknown User';
    $domain = Str::after($email, '@');

    // only allow school or whitelisted addresses
    $okSchool   = Str::endsWith($domain, 'paragoniu.edu.kh');
    $okExternal = DB::table('whitelisted_emails')->where('email', $email)->exists();
    if (! $okSchool && ! $okExternal) {
        abort(403, 'Not authorized');
    }

    // first-or-create the local user
    $user = User::firstOrCreate(
        ['email' => $email],
        [
          'name'     => $name,
          // dummy password since they login via Google
          'password' => bcrypt(Str::random(16)),
        ]
    );

    // auto-attach them to any contracts they were invited to
    Invite::where('email', $email)
          ->get()
          ->each(fn($invite) => 
              $invite->contract
                     ->members()
                     ->syncWithoutDetaching($user->id)
          );

    // issue a Sanctum personal access token for your SPA
    $token = $user->createToken('spa')->plainTextToken;

    // build the redirect back into your Next.js front-end
    $frontend = config('app.frontend_url'); // should be http://localhost:3000
    $qs = http_build_query([
        'token'    => $token,
        'redirect' => $redirect,
        'name'     => $user->name,
        'email'    => $user->email,
        'avatar'   => $googleUser->getAvatar(),
        'user_id'  => $user->id,
    ]);

    return Redirect::away("{$frontend}/auth/callback?{$qs}");
});

// 3) (unchanged) Proxy arbitrary avatars through Laravel
Route::get('/proxy-avatar', function () {
    $url = request('url');
    if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
        abort(400, 'Invalid image URL');
    }
    $resp = Http::get($url);
    return Response::make($resp->body(), 200, [
        'Content-Type'  => $resp->header('Content-Type', 'image/jpeg'),
        'Cache-Control' => 'max-age=86400',
    ]);
});

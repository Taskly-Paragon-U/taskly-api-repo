<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\Invite;
use App\Http\Controllers\ContractController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1) Kick off Google OAuth
Route::get('/auth/redirect', function (Request $request) {
    $request->session()->put('login_redirect', $request->query('redirect','/dashboard'));
    return Socialite::driver('google')->redirect();
});

// 2) Handle Google callback (NO domain checks here!)
Route::get('/auth/callback', function (Request $request) {
    $redirect   = $request->session()->pull('login_redirect','/dashboard');
    $googleUser = Socialite::driver('google')->stateless()->user();

    $email  = $googleUser->getEmail();
    $name   = $googleUser->getName()  ?? 'Unknown User';

    // find or create the local user
    $user = User::firstOrCreate(
        ['email' => $email],
        [
          'name'     => $name,
          'password' => bcrypt(Str::random(16)),
        ]
    );

    // auto-attach them to any contracts they were invited to
    Invite::where('email',$email)
          ->where('consumed',false)
          ->get()
          ->each(function($invite) use ($user) {
              $invite->contract
                     ->members()
                     ->syncWithoutDetaching($user->id);

              // mark the invite consumed so it won’t show again
              $invite->update(['consumed' => true]);
          });

    // issue Sanctum token + redirect back to Next.js
    $token    = $user->createToken('spa')->plainTextToken;
    $frontend = config('app.frontend_url');
    $qs       = http_build_query([
        'token'   => $token,
        'redirect'=> $redirect,
        'name'    => $user->name,
        'email'   => $user->email,
        'avatar'  => $googleUser->getAvatar(),
        'user_id' => $user->id,
    ]);

    return Redirect::away("{$frontend}/auth/callback?{$qs}");
});

// 3) Proxy arbitrary avatars (unchanged)
Route::get('/proxy-avatar', function () {
    $url = request('url');
    if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
        abort(400, 'Invalid image URL');
    }
    $resp = Http::get($url);
    return Response::make($resp->body(), 200, [
        'Content-Type'  => $resp->header('Content-Type'),
        'Cache-Control' => 'max-age=86400',
    ]);
});

// 4) Create contracts—inline block for @gmail.com
Route::middleware('auth:sanctum')->post('/contracts', function (Request $request) {
    $user   = $request->user();
    $domain = Str::after($user->email,'@');

    if ($domain === 'gmail.com') {
        abort(403, 'You are not allowed to create new contracts.');
    }

    return app(ContractController::class)->store($request);
});

// 5) List “my contracts” for the dashboard
Route::middleware('auth:sanctum')->get('/contracts', function (Request $request) {
    return $request->user()
                   ->contracts()
                   ->with('members')
                   ->get();
});

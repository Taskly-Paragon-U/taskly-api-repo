<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\ContractController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider within a group that
| is assigned the "api" middleware group. Sanctum will handle auth tokens.
|
*/
Route::get('invites/{token}', [InviteController::class,'show']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::get('/contracts/{contract}/invites',[InviteController::class, 'listByContract']);
    // POST /api/contracts
    Route::post('/contracts', [ContractController::class, 'store']);
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/{id}', [ContractController::class, 'show']);
    Route::post('/contracts/{id}/invite', [InviteController::class, 'invite']);
    // Accept an invite when user have done login
    Route::post('invites/{token}/accept',[InviteController::class,'accept']);
});

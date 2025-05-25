<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\TimesheetTaskController;
use App\Http\Controllers\SubmittedTimesheetController; 

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
    Route::post('/contracts', [ContractController::class, 'store']);
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/{id}', [ContractController::class, 'show']);
    Route::post('/contracts/{id}/invite', [InviteController::class, 'invite']);
    Route::post('invites/{token}/accept',[InviteController::class,'accept']);

    // timesheet‐task CRUD
    Route::get   ('/timesheet-tasks',  [TimesheetTaskController::class, 'index']);
    Route::get   ('/timesheet-tasks/{task}', [TimesheetTaskController::class, 'show']);
    Route::post  ('/timesheet-tasks',  [TimesheetTaskController::class, 'create']);
    Route::patch ('/timesheet-tasks/{task}', [TimesheetTaskController::class, 'update']);
    Route::delete('/timesheet-tasks/{task}', [TimesheetTaskController::class, 'destroy']);
    Route::post  ('/submit-timesheet', [TimesheetTaskController::class, 'submit']);
    Route::get     ('/submissions',        [SubmittedTimesheetController::class, 'index']);
    Route::delete  ('/submissions/{id}',   [SubmittedTimesheetController::class, 'destroy']);
    // Route::post('/submit-timesheet', [SubmittedTimesheetController::class, 'store']);

});

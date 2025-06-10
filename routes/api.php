<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\FileDownloadController;
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
    Route::get('/users', [UserController::class, 'index']);
    
    Route::get('/contracts/{contract}/invites',[InviteController::class, 'listByContract']);
    Route::post('/contracts', [ContractController::class, 'store']);
    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/{id}', [ContractController::class, 'show']);
    Route::post('/contracts/{id}/invite', [InviteController::class, 'invite']);
    Route::post('invites/{token}/accept',[InviteController::class,'accept']);
    
    // FIXED: Changed from {contract}/{user} to {contractId}/{userId} to match your frontend
    Route::patch('/contracts/{contractId}/members/{userId}', [ContractController::class, 'updateMember']);
    Route::delete('/contracts/{contractId}/members/{userId}', [ContractController::class, 'removeMember']);
    
    Route::get('/contracts/{contract}/submitter-supervisors', [ContractController::class, 'getSubmitterSupervisors']);
    Route::get('/contracts/{id}/supervisors', [ContractController::class, 'getSupervisors']);
    
    // timesheet‐task CRUD
    Route::get   ('/timesheet-tasks',  [TimesheetTaskController::class, 'index']);
    Route::get   ('/timesheet-tasks/{task}', [TimesheetTaskController::class, 'show']);
    Route::post  ('/timesheet-tasks',  [TimesheetTaskController::class, 'create']);
    Route::patch ('/timesheet-tasks/{task}', [TimesheetTaskController::class, 'update']);
    Route::delete('/timesheet-tasks/{task}', [TimesheetTaskController::class, 'destroy']);
    Route::post  ('/submit-timesheet', [TimesheetTaskController::class, 'submit']);
    Route::get     ('/submissions',        [SubmittedTimesheetController::class, 'index']);
    Route::delete  ('/submissions/{id}',   [SubmittedTimesheetController::class, 'destroy']);

    // For file download
    Route::get('downloads/file/{id}', [App\Http\Controllers\FileDownloadController::class, 'downloadFile']);
    Route::get('downloads/task/{taskId}', [App\Http\Controllers\FileDownloadController::class, 'downloadTaskFiles']);
    // Add this to routes/api.php for testing purposes
    Route::get('test-files', function() {
        $files = Storage::files('submitted_timesheets');
        return response()->json([
            'files' => $files,
            'storage_path' => storage_path('app/submitted_timesheets')
        ]);
    });

    // timesheet status
    Route::patch('/contracts/{contract}/timesheet-tasks/{task}/submissions/{submission}', [SubmittedTimesheetController::class, 'updateStatus']);
});

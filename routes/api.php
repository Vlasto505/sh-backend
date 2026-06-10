<?php

use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ProfilePhotoController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// Public program/call browsing
Route::prefix('v1')->group(function () {
    Route::get('/programs',                       [ProgramController::class, 'index']);
    Route::get('/programs/{program}',             [ProgramController::class, 'show']);
    Route::get('/programs/{program}/calls',       [CallController::class, 'index']);
    Route::get('/programs/{program}/calls/{call}', [CallController::class, 'show']);
});

// Authenticated routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/auth/me',     [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Profile photo
    Route::post('/auth/me/profile-photo',   [ProfilePhotoController::class, 'store']);
    Route::delete('/auth/me/profile-photo', [ProfilePhotoController::class, 'destroy']);

    // User (admin/self)
    Route::get('/user', [UserController::class, 'show']);

    // Programs (admin only for write)
    Route::post('/programs',              [ProgramController::class, 'store']);
    Route::patch('/programs/{program}',   [ProgramController::class, 'update']);
    Route::delete('/programs/{program}',  [ProgramController::class, 'destroy']);

    // Calls (scoped to program)
    Route::post('/programs/{program}/calls',             [CallController::class, 'store']);
    Route::patch('/programs/{program}/calls/{call}',     [CallController::class, 'update']);
    Route::delete('/programs/{program}/calls/{call}',    [CallController::class, 'destroy']);

    // Applications
    Route::get('/applications',                              [ApplicationController::class, 'index']);
    Route::post('/calls/{call}/applications',                [ApplicationController::class, 'store']);
    Route::get('/applications/{application}',               [ApplicationController::class, 'show']);
    Route::patch('/applications/{application}',             [ApplicationController::class, 'update']);
    Route::post('/applications/{application}/submit',       [ApplicationController::class, 'submit']);
    Route::post('/applications/{application}/decide',       [ApplicationController::class, 'decide']);
    Route::delete('/applications/{application}',            [ApplicationController::class, 'destroy']);

    // Organizations
    Route::get('/organizations',               [OrganizationController::class, 'index']);
    Route::post('/organizations',              [OrganizationController::class, 'store']);
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
    Route::patch('/organizations/{organization}', [OrganizationController::class, 'update']);

    // Attachments for applications
    Route::get('/applications/{application}/attachments',           [AttachmentController::class, 'index']);
    Route::post('/applications/{application}/attachments',          [AttachmentController::class, 'store']);
    Route::get('/attachments/{attachment:public_id}/link',          [AttachmentController::class, 'link']);
    Route::delete('/attachments/{attachment:public_id}',            [AttachmentController::class, 'destroy']);
});

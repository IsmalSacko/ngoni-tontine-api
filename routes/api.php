<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CycleController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TontineController;
use App\Http\Controllers\Api\TontineMemberController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::patch('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/upload-avatar', [AuthController::class, 'uploadAvatar']);
    Route::post('/change-pin', [AuthController::class, 'changePin']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // Groupes (associations) : membres communs à plusieurs caisses
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{group}', [GroupController::class, 'show']);
    Route::patch('/groups/{group}', [GroupController::class, 'update']);
    Route::get('/groups/{group}/statement', [GroupController::class, 'statement']);
    Route::post('/groups/{group}/members', [GroupController::class, 'storeMember']);
    Route::patch('/groups/{group}/members/{member}', [GroupController::class, 'updateMember']);
    Route::delete('/groups/{group}/members/{member}', [GroupController::class, 'destroyMember']);

    Route::get('/tontines', [TontineController::class, 'index']);
    Route::post('/tontines', [TontineController::class, 'store']);
    Route::patch('/tontines/{tontine}', [TontineController::class, 'update']);
    Route::get('/tontines/{tontine}', [TontineController::class, 'show']);

    Route::get('/tontines/{tontine}/members', [TontineMemberController::class, 'index']);
    Route::get('/tontines/{tontine}/members/paused', [TontineMemberController::class, 'paused']);
    Route::post('/tontines/{tontine}/members', [TontineMemberController::class, 'store']);
    Route::patch('/tontines/{tontine}/members/{member}', [TontineMemberController::class, 'update']);
    Route::delete('/tontines/{tontine}/members/{member}', [TontineMemberController::class, 'destroy']);
    Route::patch('/tontines/{tontine}/members/{member}/pause', [TontineMemberController::class, 'pause']);
    Route::patch('/tontines/{tontine}/members/{member}/resume', [TontineMemberController::class, 'resume']);

    Route::get('/tontines/{tontine}/payments', [PaymentController::class, 'index']);

    // Dépenses d'une caisse commune
    Route::get('/tontines/{tontine}/expenses', [ExpenseController::class, 'index']);
    Route::post('/tontines/{tontine}/expenses', [ExpenseController::class, 'store']);
    Route::delete('/tontines/{tontine}/expenses/{expense}', [ExpenseController::class, 'destroy']);
    Route::post('/payments', [PaymentController::class, 'store']);

    // Gestion des cycles
    Route::get('/tontines/{tontine}/cycles', [CycleController::class, 'index']);
    Route::get('/tontines/{tontine}/cycles/{cycle}/summary', [CycleController::class, 'summary']);
    Route::post('/tontines/{tontine}/cycles/{cycle}/close', [CycleController::class, 'close']);
    Route::patch('/tontines/{tontine}/cycles/{cycle}/beneficiary', [CycleController::class, 'setBeneficiary']);
});

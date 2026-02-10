<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/api', function () {
    Route::get('/api', [AuthController::class, 'successResponse']);
});

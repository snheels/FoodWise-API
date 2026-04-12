<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\UserController;

// Google Auth Routes
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Protected routes (butuh token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [UserController::class, 'me']);

    // Dashboard
    Route::get('/dashboard', [FoodController::class, 'dashboard']);
    Route::get('/dashboard/expiring-soon', [FoodController::class, 'expiringSoon']);
    Route::get('/dashboard/chart', [FoodController::class, 'wasteChart']);

    // Foods
    Route::get('/foods', [FoodController::class, 'index']);
    Route::post('/foods', [FoodController::class, 'store']);
    Route::get('/foods/{id}', [FoodController::class, 'show']);
    Route::patch('/foods/{id}/consume', [FoodController::class, 'consume']);
    Route::patch('/foods/{id}/discard', [FoodController::class, 'discard']);
    Route::post('/foods/{id}/remind', [FoodController::class, 'remind']);
});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FoodController;
use App\Http\Controllers\UserController;
use Laravel\Socialite\Facades\Socialite;

Route::get('/user', [UserController::class, 'index']);

// SEMENTARA
// dashboard
Route::get('/dashboard', [FoodController::class, 'dashboard']);
Route::get('/dashboard/expiring-soon', [FoodController::class, 'expiringSoon']);
Route::get('/dashboard/chart', [FoodController::class, 'wasteChart']);
// semua data
Route::get('/foods', [FoodController::class, 'index']);
// tambah data
Route::post('/foods', [FoodController::class, 'store']);
// detail
Route::get('/foods/{id}', [FoodController::class, 'show']);
Route::patch('/foods/{id}/consume', [FoodController::class, 'consume']);
Route::patch('/foods/{id}/discard', [FoodController::class, 'discard']);
Route::patch('/foods/{id}/remind', [FoodController::class, 'remind']);

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

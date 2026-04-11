<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FoodController;
use Laravel\Socialite\Facades\Socialite;

Route::get('/user', [UserController::class, 'index']);

Route::apiResource('foods', FoodController::class);
// tambahan fitur
Route::get('foods/dashboard', [FoodController::class, 'dashboard']);
Route::get('foods/expiring-soon', [FoodController::class, 'expiringSoon']);

Route::patch('foods/{id}/consume', [FoodController::class, 'consume']);
Route::patch('foods/{id}/discard', [FoodController::class, 'discard']);


// GOOGLE AUTH
// Route::get('auth/google', function () {
//     return Socialite::driver('google')
//         ->scopes(['https://www.googleapis.com/auth/calendar'])
//         ->redirect();
// });

// Route::get('auth/google/callback', function () {
//     $user = Socialite::driver('google')->stateless()->user();

//     session(['google_token' => $user->token]);

//     return response()->json($user);
// });

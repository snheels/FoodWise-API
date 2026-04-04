<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FoodController;

Route::get('/user', [UserController::class, 'index']);

Route::apiResource('foods', FoodController::class);
// tambahan fitur
Route::get('foods/dashboard', [FoodController::class, 'dashboard']);
Route::get('foods/expiring-soon', [FoodController::class, 'expiringSoon']);

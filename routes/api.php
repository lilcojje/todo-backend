<?php
// routes/api.php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TodoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Todo routes
    Route::get('/todos', [TodoController::class, 'index']);
    Route::post('/todos', [TodoController::class, 'store']);
    Route::get('/todos/{todo}', [TodoController::class, 'show']);
    Route::put('/todos/{todo}', [TodoController::class, 'update']);
    Route::delete('/todos/{todo}', [TodoController::class, 'destroy']);
    Route::get('/todos/search/{term}', [TodoController::class, 'search']);
    Route::get('/todos/filter/{filter}', [TodoController::class, 'filterByStatus']);
    Route::get('/todos/upcoming/overview', [TodoController::class, 'getUpcoming']);
});

// Health check route
Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'message' => 'API is running']);
});
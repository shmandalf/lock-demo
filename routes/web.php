<?php

use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::prefix('api/tasks')->group(function () {
    Route::post('/create', [TaskController::class, 'create']);
    Route::post('/clear', [TaskController::class, 'clear']);
    Route::get('/health', [TaskController::class, 'health']);
});

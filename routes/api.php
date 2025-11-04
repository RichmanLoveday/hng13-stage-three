<?php

use App\Http\Controllers\NewsAgentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::post('/news-agent', [NewsAgentController::class, 'handle']);
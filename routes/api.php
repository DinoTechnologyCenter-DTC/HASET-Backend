<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\Api\PaymentController;

// Apply CORS middleware to all API routes
Route::middleware(['cors'])->group(function () {
    Route::post('/payment/initiate', [PaymentController::class, 'initiate']);
    Route::post('/payment/callback', [PaymentController::class, 'callback']);
    Route::get('/payment/status', [PaymentController::class, 'checkStatus']);
    Route::post('/payment/cancel', [PaymentController::class, 'cancel']);
});

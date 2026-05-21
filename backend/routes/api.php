<?php

use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/ping', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Backend is connected smoothly to Frontend!',
    ]);
});

// Public subscription/payment routes
Route::get('/subscription/packages', [SubscriptionController::class, 'index']);
Route::post('/payments/callback', [PaymentCallbackController::class, 'handleCallback']);

// Protected subscription routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/subscription/status', [SubscriptionController::class, 'status']);
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
});

<?php

use App\Http\Controllers\BookingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());

    Route::get('/bookings',                      [BookingController::class, 'index']);
    Route::post('/bookings',                     [BookingController::class, 'store']);
    Route::get('/bookings/{reservation}',        [BookingController::class, 'show']);
    Route::delete('/bookings/{reservation}',     [BookingController::class, 'destroy']);
    Route::get('/bookings/{reservation}/refund', [BookingController::class, 'refundPreview']);
});

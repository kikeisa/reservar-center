<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])
    ->name('home')
    ->middleware('auth');

Route::middleware('auth')->prefix('client')->name('client.')->group(function () {
    Route::get('/services',                      [App\Http\Controllers\ClientBookingController::class, 'services'])->name('services');
    Route::get('/bookings',                      [App\Http\Controllers\ClientBookingController::class, 'list'])->name('bookings.list');
    Route::post('/bookings',                     [App\Http\Controllers\ClientBookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{reservation}/refund', [App\Http\Controllers\ClientBookingController::class, 'refundPreview'])->name('bookings.refund');
    Route::delete('/bookings/{reservation}',     [App\Http\Controllers\ClientBookingController::class, 'cancel'])->name('bookings.cancel');
});

Route::middleware(['auth', 'is_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\UserController::class, 'index'])->name('dashboard');
    Route::get('/users', [App\Http\Controllers\UserController::class, 'list'])->name('users.list');
    Route::post('/users', [App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [App\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [App\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/reservations',                  [App\Http\Controllers\AdminReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/list',             [App\Http\Controllers\AdminReservationController::class, 'list'])->name('reservations.list');
    Route::delete('/reservations/{reservation}', [App\Http\Controllers\AdminReservationController::class, 'destroy'])->name('reservations.destroy');
});

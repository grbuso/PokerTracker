<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoundController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('rounds/create', [RoundController::class, 'create'])->name('rounds.create');
    Route::post('rounds', [RoundController::class, 'store'])->name('rounds.store');
    Route::get('rounds/{round}', [RoundController::class, 'show'])->name('rounds.show');
    Route::post('rounds/{round}/snapshots', [RoundController::class, 'storeSnapshot'])->name('rounds.snapshots.store');
});

require __DIR__.'/settings.php';

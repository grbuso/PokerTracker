<?php

use App\Http\Controllers\RoundController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('rounds/create', [RoundController::class, 'create']);
    Route::post('rounds', [RoundController::class, 'store']);
    Route::get('rounds/{rounds}', [RoundController::class, 'show']);
    Route::post('rounds/{round}/snapshots', [RoundController::class, 'storeSnapshot']);
});

require __DIR__.'/settings.php';

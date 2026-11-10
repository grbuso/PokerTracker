<?php

use App\Http\Controllers\Admin\PlayerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscordInteractionController;
use App\Http\Controllers\RoundController;
use App\Http\Controllers\RoundSubmissionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('discord/interactions', DiscordInteractionController::class)
    ->name('discord.interactions');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('rounds/{round}', [RoundController::class, 'show'])
        ->whereNumber('round')
        ->name('rounds.show');
});

Route::middleware(['auth', 'verified', 'can:manage-game'])->group(function () {
    Route::get('rounds/create', [RoundController::class, 'create'])->name('rounds.create');
    Route::post('rounds', [RoundController::class, 'store'])->name('rounds.store');
    Route::post('rounds/{round}/close', [RoundController::class, 'close'])
        ->whereNumber('round')
        ->name('rounds.close');
    Route::delete('rounds/{round}', [RoundController::class, 'destroy'])
        ->whereNumber('round')
        ->name('rounds.destroy');
    Route::post('rounds/{round}/snapshots', [RoundController::class, 'storeSnapshot'])
        ->whereNumber('round')
        ->name('rounds.snapshots.store');
    Route::get('rounds/{round}/submissions', [RoundSubmissionController::class, 'index'])
        ->whereNumber('round')
        ->name('rounds.submissions.index');
    Route::delete('rounds/{round}/submissions', [RoundSubmissionController::class, 'destroy'])
        ->whereNumber('round')
        ->name('rounds.submissions.destroy');

    Route::resource('admin/players', PlayerController::class)
        ->except(['show', 'create', 'edit'])
        ->names('admin.players');
});

require __DIR__.'/settings.php';

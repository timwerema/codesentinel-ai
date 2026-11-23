<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? to_route('dashboard') : to_route('login'))->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('analyses', [AnalysisController::class, 'index'])->name('analyses.index');
    Route::get('analyses/new', [AnalysisController::class, 'create'])->name('analyses.create');
    Route::post('analyses', [AnalysisController::class, 'store'])->middleware('throttle:30,1')->name('analyses.store');
    Route::get('analyses/{analysis}', [AnalysisController::class, 'show'])->name('analyses.show');
    Route::get('analyses/{analysis}/status', [AnalysisController::class, 'status'])->middleware('throttle:120,1')->name('analyses.status');
    Route::post('analyses/{analysis}/retry', [AnalysisController::class, 'retry'])->middleware('throttle:30,1')->name('analyses.retry');
    Route::delete('analyses/{analysis}', [AnalysisController::class, 'destroy'])->name('analyses.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

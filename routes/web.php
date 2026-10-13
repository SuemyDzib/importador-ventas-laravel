<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/importaciones');

Route::controller(ImportController::class)->prefix('importaciones')->name('imports.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('/plantilla', 'template')->name('template');           // antes de {import}
    Route::get('/{import}', 'show')->name('show');
    Route::get('/{import}/errores', 'exportErrors')->name('errors');
    Route::delete('/{import}', 'destroy')->name('destroy');
});

Route::get('/tablero', DashboardController::class)->name('dashboard');

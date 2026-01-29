<?php

use Illuminate\Support\Facades\Route;
use WestWallTech\FaviconGenerator\Http\Controllers\FaviconController;

Route::prefix('westwalltech/favicon-generator')->group(function () {
    Route::get('/', [FaviconController::class, 'index'])->name('favicon-generator.index');
    Route::post('/generate', [FaviconController::class, 'generate'])->name('favicon-generator.generate');
    Route::get('/preview', [FaviconController::class, 'preview'])->name('favicon-generator.preview');
    Route::post('/clear', [FaviconController::class, 'clear'])->name('favicon-generator.clear');
});

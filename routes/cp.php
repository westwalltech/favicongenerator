<?php

use Illuminate\Support\Facades\Route;
use WestWallTech\FaviconGenerator\Http\Controllers\FaviconController;

Route::prefix('favicon-generator')->group(function () {
    Route::get('/', [FaviconController::class, 'index'])->name('favicon-generator.index');
    Route::post('/generate', [FaviconController::class, 'generate'])->name('favicon-generator.generate');
    Route::post('/save', [FaviconController::class, 'saveSettings'])->name('favicon-generator.save');
    Route::get('/preview', [FaviconController::class, 'preview'])->name('favicon-generator.preview');
    Route::post('/clear', [FaviconController::class, 'clear'])->name('favicon-generator.clear');
});

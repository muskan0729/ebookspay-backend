<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\EbookController;
use App\Http\Controllers\Api\Admin\CategoryController;

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {

        // Ebooks
        Route::get('/ebooks', [EbookController::class, 'index']);
        Route::post('/ebooks', [EbookController::class, 'store']);
        Route::get('/ebooks/{id}', [EbookController::class, 'show']);
        Route::put('/ebooks/{id}', [EbookController::class, 'update']);
        Route::delete('/ebooks/{id}', [EbookController::class, 'destroy']);

        // Categories
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    });

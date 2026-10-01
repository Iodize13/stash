<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/user', fn (Request $request) => new UserResource($request->user()));

    Route::get('/articles', [ArticleController::class, 'index'])
        ->middleware('abilities:articles:read')
        ->name('api.articles.index');
    Route::get('/articles/{article}', [ArticleController::class, 'show'])
        ->middleware('abilities:articles:read')
        ->whereNumber('article')
        ->name('api.articles.show');
    Route::post('/articles', [ArticleController::class, 'store'])
        ->middleware('abilities:articles:write')
        ->name('api.articles.store');
});

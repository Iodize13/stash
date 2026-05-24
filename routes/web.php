<?php

use App\Http\Controllers\DemoLoginController;
use App\Http\Controllers\ExportAllHighlightsController;
use App\Http\Controllers\ExportHighlightsController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PublicCollectionController;
use App\Http\Controllers\SaveLinkPopupController;
use App\Livewire\Highlights;
use App\Livewire\Library;
use App\Livewire\Reader;
use App\Models\Article;
use App\Models\Highlight;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

Route::post('/demo', DemoLoginController::class)
    ->middleware(['guest', 'throttle:10,1'])
    ->name('demo');

Route::controller(PublicCollectionController::class)->prefix('c/{slug}')->group(function () {
    Route::get('/', 'show')->name('collections.show');
    Route::get('/feed.atom', 'feed')->name('collections.feed');
    Route::get('/highlights.md', 'export')->name('collections.export');
});

Route::middleware('auth')->group(function () {
    Route::get('/library', Library::class)
        ->middleware('can:viewAny,'.Article::class)
        ->name('library');

    Route::get('/save', [SaveLinkPopupController::class, 'show'])->name('save');
    Route::post('/save', [SaveLinkPopupController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('save.store');

    Route::get('/highlights', Highlights::class)
        ->middleware('can:viewAny,'.Highlight::class)
        ->name('highlights');

    Route::get('/highlights.md', ExportAllHighlightsController::class)->name('highlights.export');

    Route::get('/articles/{article}', Reader::class)->name('articles.show');

    Route::get('/articles/{article}/highlights.md', ExportHighlightsController::class)
        ->name('articles.highlights.export');
});

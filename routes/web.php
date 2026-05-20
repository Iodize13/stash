<?php

use App\Http\Controllers\ExportHighlightsController;
use App\Http\Controllers\PublicCollectionController;
use App\Livewire\Library;
use App\Livewire\Reader;
use App\Models\Article;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::controller(PublicCollectionController::class)->prefix('c/{slug}')->group(function () {
    Route::get('/', 'show')->name('collections.show');
    Route::get('/feed.atom', 'feed')->name('collections.feed');
    Route::get('/highlights.md', 'export')->name('collections.export');
});

Route::middleware('auth')->group(function () {
    Route::get('/library', Library::class)
        ->middleware('can:viewAny,'.Article::class)
        ->name('library');

    Route::get('/articles/{article}', Reader::class)->name('articles.show');

    Route::get('/articles/{article}/highlights.md', ExportHighlightsController::class)
        ->name('articles.highlights.export');
});

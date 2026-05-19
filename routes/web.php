<?php

use App\Http\Controllers\ExportHighlightsController;
use App\Livewire\Library;
use App\Livewire\Reader;
use App\Models\Article;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/library', Library::class)
        ->middleware('can:viewAny,'.Article::class)
        ->name('library');

    Route::get('/articles/{article}', Reader::class)->name('articles.show');

    Route::get('/articles/{article}/highlights.md', ExportHighlightsController::class)
        ->name('articles.highlights.export');
});

<?php

use App\Livewire\Library;
use App\Models\Article;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/library', Library::class)
    ->middleware(['auth', 'can:viewAny,'.Article::class])
    ->name('library');

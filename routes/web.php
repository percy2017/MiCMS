<?php

use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])
    ->middleware('throttle:public-pages')
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('admin', 'admin')->name('admin');
});

Route::get('sitemap.xml', [SitemapController::class, 'index'])
    ->middleware('throttle:public-pages')
    ->name('sitemap');

Route::get('{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|api|docs|login|register|forgot-password|two-factor|user|settings|storage|livewire|build|up).*$')
    ->middleware('throttle:public-pages')
    ->name('pages.show');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';

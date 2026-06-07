<?php

use App\Http\Controllers\Page\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('admin', 'admin')->name('admin');
});

Route::get('{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|login|register|forgot-password|two-factor|user|settings|storage|livewire|build|up).*$')
    ->name('pages.show');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';

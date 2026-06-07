<?php

use App\Http\Controllers\Media\MediaController;
use App\Http\Controllers\Media\MediaUploadController;
use App\Http\Controllers\Menu\MenuController;
use App\Http\Controllers\Menu\MenuItemController;
use App\Http\Controllers\Page\PageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaUploadController::class, 'store'])->name('media.store');
    Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
    Route::get('media/{media}/edit', [MediaController::class, 'edit'])->name('media.edit');
    Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    Route::get('paginas', [PageController::class, 'index'])->name('paginas.index');
    Route::post('paginas', [PageController::class, 'store'])->name('paginas.store');
    Route::get('paginas/{page}/editar', [PageController::class, 'edit'])->name('paginas.edit');
    Route::patch('paginas/{page}', [PageController::class, 'update'])->name('paginas.update');
    Route::delete('paginas/{page}', [PageController::class, 'destroy'])->name('paginas.destroy');
    Route::post('paginas/{page}/home', [PageController::class, 'setHome'])->name('paginas.set-home');
    Route::delete('paginas/{page}/home', [PageController::class, 'unsetHome'])->name('paginas.unset-home');

    Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
    Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
    Route::get('menus/{menu}/editar', [MenuController::class, 'edit'])->name('menus.edit');
    Route::patch('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
    Route::delete('menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');

    Route::post('menus/{menu}/items', [MenuItemController::class, 'store'])->name('menus.items.store');
    Route::patch('menus/{menu}/items/{item}', [MenuItemController::class, 'update'])->name('menus.items.update');
    Route::delete('menus/{menu}/items/{item}', [MenuItemController::class, 'destroy'])->name('menus.items.destroy');
    Route::post('menus/{menu}/items/reorder', [MenuItemController::class, 'reorder'])->name('menus.items.reorder');
});

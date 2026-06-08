<?php

use App\Http\Controllers\Media\MediaController;
use App\Http\Controllers\Media\MediaUploadController;
use App\Http\Controllers\Menu\MenuController;
use App\Http\Controllers\Menu\MenuItemController;
use App\Http\Controllers\Package\PackageController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Role\RoleController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\User\UserController;
use App\Services\ReverbMonitorService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('reverb', function () {
        return Inertia::render('admin/reverb', [
            'stats' => app(ReverbMonitorService::class)->getStats(),
        ]);
    })->name('reverb');

    Route::get('reverb/stats', function () {
        return response()->json(
            app(ReverbMonitorService::class)->getStats()
        );
    })->name('reverb.stats');

    Route::post('reverb/reset', function () {
        app(ReverbMonitorService::class)->reset();

        return back();
    })->name('reverb.reset');

    Route::prefix('schedule')->name('schedule.')->group(function () {
        Route::get('/', [ScheduleController::class, 'index'])->name('index');
        Route::get('/commands', [ScheduleController::class, 'commands'])->name('commands');
        Route::get('/crear', [ScheduleController::class, 'create'])->name('create');
        Route::post('/', [ScheduleController::class, 'store'])->name('store');
        Route::get('/{task}/editar', [ScheduleController::class, 'edit'])->name('edit');
        Route::patch('/{task}', [ScheduleController::class, 'update'])->name('update');
        Route::delete('/{task}', [ScheduleController::class, 'destroy'])->name('destroy');
        Route::patch('/{task}/toggle', [ScheduleController::class, 'toggle'])->name('toggle');
        Route::post('/{task}/run', [ScheduleController::class, 'run'])->name('run');
        Route::get('/{task}/history', [ScheduleController::class, 'history'])->name('history');
    });

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

    Route::prefix('paquetes')->name('paquetes.')->group(function () {
        Route::get('/', [PackageController::class, 'index'])->name('index');
        Route::get('/{slug}/editar', [PackageController::class, 'edit'])->name('edit');
        Route::patch('/{slug}/toggle', [PackageController::class, 'toggle'])->name('toggle');
    });

    Route::prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/crear', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/editar', [UserController::class, 'edit'])->name('edit');
        Route::patch('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/crear', [RoleController::class, 'create'])->name('create');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('/{role}/editar', [RoleController::class, 'edit'])->name('edit');
        Route::patch('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('permisos')->name('permisos.')->group(function () {
        Route::get('/', [PermissionController::class, 'index'])->name('index');
        Route::post('/', [PermissionController::class, 'store'])->name('store');
        Route::delete('/{permission}', [PermissionController::class, 'destroy'])->name('destroy');
    });
});

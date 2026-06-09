<?php

use App\Http\Controllers\Api\UserApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Stateless JSON endpoints. Mounted under /api and loaded with the `api`
| middleware group (defined by `bootstrap/app.php` -> withRouting api:).
|
| Scramble auto-generates OpenAPI specs from these routes, controllers,
| FormRequests and Resources. Visit /docs in the admin to browse them.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', fn (): array => [
        'status' => 'ok',
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ])->name('health');

    Route::middleware(['auth', 'verified'])->group(function (): void {
        Route::get('roles', [UserApiController::class, 'roles'])->name('users.roles');
        Route::apiResource('users', UserApiController::class)->names('users');
    });
});

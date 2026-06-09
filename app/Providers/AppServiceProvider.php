<?php

namespace App\Providers;

use App\LogViewer\LogViewerService;
use App\Models\User;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('log-viewer', LogViewerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerPolicies();
        $this->registerGates();
        $this->registerRateLimiters();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }

    /**
     * Define gates for feature-level authorization.
     */
    protected function registerGates(): void
    {
        Gate::define('viewApiDocs', function (User $user): bool {
            return $user->hasRole('admin');
        });
    }

    /**
     * Configure rate limiters for public endpoints.
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('public-pages', function (Request $request): array {
            $key = $request->ip() ?? 'unknown';

            return [
                Limit::perMinute(60)->by($key),
                Limit::perHour(1000)->by($key),
            ];
        });
    }
}

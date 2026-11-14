<?php

namespace App\Providers;

use App\Listeners\LogSsrRenderFailure;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Ssr\SsrRenderFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(SsrRenderFailed::class, LogSsrRenderFailure::class);

        $this->configureDefaults();
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

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );

        RateLimiter::for(
            'join',
            fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
        );

        RateLimiter::for(
            'household',
            fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip())
        );

        RateLimiter::for(
            'invites',
            fn (Request $request) => Limit::perMinute(6)->by($request->user()?->id ?: $request->ip())
        );
    }
}

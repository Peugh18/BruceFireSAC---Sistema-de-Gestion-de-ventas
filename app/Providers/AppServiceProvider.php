<?php

namespace App\Providers;

use App\Contracts\GreClientInterface;
use App\Contracts\SunatClientInterface;
use App\Models\Sede;
use App\Observers\SedeObserver;
use App\Services\Billing\GreApiClient;
use App\Services\Billing\GreenterSunatClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SunatClientInterface::class, GreenterSunatClient::class);
        $this->app->bind(GreClientInterface::class, GreApiClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Proxies del hosting desde el .env (X4); se lee de config para que
        // funcione también con `config:cache`.
        $proxies = (string) config('seguridad.proxies_confiables');
        TrustProxies::at($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))));

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Sede::observe(SedeObserver::class);
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
}

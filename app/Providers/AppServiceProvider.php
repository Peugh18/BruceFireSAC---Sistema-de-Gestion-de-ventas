<?php

namespace App\Providers;

use App\Contracts\GreClientInterface;
use App\Contracts\SunatClientInterface;
use App\Models\Sede;
use App\Observers\SedeObserver;
use App\Services\Billing\GreApiClient;
use App\Services\Billing\GreenterSunatClient;
use App\Services\Billing\GreSimuladoClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
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
        // La guía simulada solo existe en desarrollo y pruebas: en producción la
        // bandera se ignora y se usa siempre la API real de SUNAT.
        $this->app->bind(GreClientInterface::class, fn () => config('billing.gre.simulado') && $this->app->environment('local', 'testing')
            ? new GreSimuladoClient
            : new GreApiClient);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        DevCommands::artisan('schedule:work', 'scheduler');

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

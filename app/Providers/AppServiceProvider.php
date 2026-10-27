<?php

namespace App\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Providers\FakeAiProvider;
use App\AI\Providers\GeminiProvider;
use App\Services\Tenant\TenantManager;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantManager::class, function () {
            return new TenantManager();
        });

        $this->app->bind(AiProviderInterface::class, function () {
            if ($this->app->environment('testing') || config('ai.provider') === 'fake') {
                return new FakeAiProvider();
            }

            return new GeminiProvider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            if ($this->app->bound('request')) {
                $req = $this->app->make('request');
                $host = $req->getHost();
                $isLocalNetwork = in_array($host, ['localhost', '127.0.0.1']) || str_starts_with($host, '192.168.') || str_starts_with($host, '10.') || str_starts_with($host, '172.');

                if (!$isLocalNetwork || $req->server('HTTP_X_FORWARDED_PROTO') === 'https' || $req->header('X-Forwarded-Proto') === 'https') {
                    \Illuminate\Support\Facades\URL::forceScheme('https');
                }
            }
        } catch (\Throwable $e) {
            // Ignorar fallos de resolución temprana de request
        }
    }
}

<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->appendToGroup('web', [
            SecurityHeadersMiddleware::class,
            \App\Http\Middleware\EnsurePasswordIsChanged::class,
        ]);

        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            SetCurrentTenant::class
        );

        $middleware->alias([
            'tenant' => SetCurrentTenant::class,
            'role' => CheckRole::class,
            'must_change_password' => \App\Http\Middleware\EnsurePasswordIsChanged::class,
            'restaurant' => \App\Http\Middleware\EnsureBusinessIsRestaurant::class,
            'print.agent' => \App\Http\Middleware\PrintAgentToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

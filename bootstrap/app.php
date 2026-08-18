<?php

use App\Support\Clients\ClientEnvironmentLoader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

$app->afterLoadingEnvironment(function () use ($app): void {
    /*
     * Cached configuration already contains the fully resolved selected-client
     * environment and merged PHP configuration. Re-loading client .env values
     * would contradict Laravel's config-cache model.
     *
     * Tests also intentionally run without loading a real selected client.
     */
    if (
        $app->configurationIsCached()
        || Env::get('APP_ENV') === 'testing'
    ) {
        return;
    }

    (new ClientEnvironmentLoader())->load($app->basePath());
});

return $app;
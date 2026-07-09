<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*') && ! $request->is('api')) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return \App\Support\SafeJson::message(
                    $e->getMessage() ?: 'Erro na requisição.',
                    $e->getStatusCode()
                );
            }

            $message = $e->getMessage() ?: 'Erro interno.';
            if (! config('app.debug')) {
                $message = 'Erro interno do servidor.';
            }

            return \App\Support\SafeJson::message($message, 500);
        });
    })->create();

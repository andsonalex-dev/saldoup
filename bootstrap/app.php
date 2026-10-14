<?php

use App\Http\Middleware\EnsureUserActive;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureUserActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $exception): void {
            Log::error('Falha em operação de banco de dados.', ['sqlstate' => $exception->errorInfo[0] ?? $exception->getCode()]);
        })->stop();
        $exceptions->dontFlash(['token']);
        $exceptions->respond(function (Response $response): Response {
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

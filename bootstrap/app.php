<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $code = 'INTERNAL_ERROR';
                $status = 500;
                $message = $e->getMessage();

                if ($e instanceof ValidationException) {
                    $code = 'VALIDATION_FAILED';
                    $status = 422;
                    $message = 'Переданные данные не прошли проверку.';
                } elseif ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
                    $code = 'NOT_FOUND';
                    $status = 404;
                    $message = 'Запрашиваемый ресурс не найден.';
                } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $code = 'UNAUTHORIZED';
                    $status = 401;
                    $message = 'Необходима авторизация.';
                } elseif ($e instanceof InvalidArgumentException || $e instanceof DomainException) {
                    $code = 'BAD_REQUEST';
                    $status = 400;
                }

                if (config('app.debug') && $status === 500) {
                    // Let Laravel handle it to show full stack trace in dev
                    return null;
                }

                if ($status === 500 && !config('app.debug')) {
                    $message = 'Внутренняя ошибка сервера.';
                }

                $response = [
                    'error' => [
                        'code' => $code,
                        'message' => $message,
                    ],
                ];

                if ($e instanceof ValidationException) {
                    $response['error']['details'] = $e->errors();
                }

                return response()->json($response, $status);
            }

            return null;
        });
    })->create();

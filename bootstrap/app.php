<?php

declare(strict_types=1);

use App\Modules\Billing\Presentation\Middleware\EnsureActiveCompanySubscription;
use App\Shared\Presentation\Middleware\SetLocaleFromHeader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SetLocaleFromHeader::class);
        $middleware->append(EnsureActiveCompanySubscription::class);
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
                    $message = __('api.errors.validation_failed');
                } elseif ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
                    $code = 'NOT_FOUND';
                    $status = 404;
                    $message = __('api.errors.not_found');
                } elseif ($e instanceof AuthenticationException) {
                    $code = 'UNAUTHORIZED';
                    $status = 401;
                    $message = __('api.errors.unauthorized');
                } elseif ($e instanceof AccessDeniedHttpException || $e instanceof AuthorizationException) {
                    $code = 'FORBIDDEN';
                    $status = 403;
                    $message = __('api.errors.forbidden');
                } elseif ($e instanceof TooManyRequestsHttpException) {
                    $code = 'TOO_MANY_REQUESTS';
                    $status = 429;
                    $message = __('api.errors.too_many_requests');
                } elseif ($e instanceof InvalidArgumentException || $e instanceof DomainException) {
                    $code = 'BAD_REQUEST';
                    $status = 400;
                }

                if (config('app.debug') && $status === 500) {
                    // Let Laravel handle it to show full stack trace in dev
                    return null;
                }

                if ($status === 500 && !config('app.debug')) {
                    $message = __('api.errors.internal');
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

                return response()->json(
                    $response,
                    $status,
                    $e instanceof HttpExceptionInterface ? $e->getHeaders() : [],
                );
            }

            return null;
        });
    })->create();

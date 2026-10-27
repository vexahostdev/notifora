<?php

use App\Http\Middleware\ApiRateLimit;
use App\Http\Middleware\IdempotencyGuard;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SetCurrentEnvironment;
use App\Http\Middleware\SetRequestId;
use App\Http\Middleware\ValidateApiKey;
use App\Http\Middleware\VerifyInternalSecret;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'api.key' => ValidateApiKey::class,
            'api.rate' => ApiRateLimit::class,
            'api.idem' => IdempotencyGuard::class,
            'internal' => VerifyInternalSecret::class,
            'env.set' => SetCurrentEnvironment::class,
            'role' => RequireRole::class,
        ]);
        $middleware->api(prepend: [SetRequestId::class]);
        // Environment aktif harus terpasang sebelum route-model binding agar query ter-scope.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentEnvironment::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('console.dashboard'));
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: ['api/payment/*', 'api/webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isApi = fn (Request $r) => $r->is('v1/*');
        $exceptions->shouldRenderJsonWhen(fn (Request $r, Throwable $e) => $isApi($r) || $r->expectsJson());

        $exceptions->render(function (ValidationException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return ApiResponse::error('validation_failed', 'The given data was invalid.', 422, $e->errors());
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return ApiResponse::error('unauthenticated', 'Invalid or missing API key.', 401);
            }
        });
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                return ApiResponse::error('not_found', 'Resource not found.', 404);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $r) use ($isApi) {
            if ($isApi($r)) {
                $code = match ($e->getStatusCode()) {
                    403 => 'forbidden', 429 => 'rate_limited', 413 => 'validation_failed', default => 'server_error',
                };

                return ApiResponse::error($code, $e->getMessage() ?: 'Error', $e->getStatusCode());
            }
        });
    })->create();

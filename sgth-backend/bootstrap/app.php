<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'primer-login' => \App\Http\Middleware\PrimerLoginMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->render(function (ValidationException $e) {
            return ApiResponse::error(
                'Los datos enviados no son válidos.',
                $e->errors(),
                422,
            );
        });

        $exceptions->render(function (AuthorizationException $e) {
            return ApiResponse::noAutorizado();
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
            return ApiResponse::noAutorizado();
        });

        $exceptions->render(function (AuthenticationException $e) {
            return ApiResponse::error(
                'No autenticado. Por favor inicie sesión.',
                null,
                401,
            );
        });

        $exceptions->render(function (ModelNotFoundException $e) {
            return ApiResponse::noEncontrado();
        });

        $exceptions->render(function (NotFoundHttpException $e) {
            return ApiResponse::noEncontrado('La ruta solicitada no existe.');
        });

        $exceptions->render(function (\App\Exceptions\ReglaNegocioException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        });

        $exceptions->render(function (\Throwable $e) {
            if (config('app.debug')) {
                return ApiResponse::error(
                    $e->getMessage(),
                    ['trace' => $e->getTraceAsString()],
                    500,
                );
            }
            return ApiResponse::errorServidor();
        });
    })
    ->create();

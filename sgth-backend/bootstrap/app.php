<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
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

        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e) {
            return ApiResponse::noAutorizado();
        });

        $exceptions->render(function (AuthenticationException $e) {
            $mensaje = $e->getMessage() === 'Unauthenticated.' 
                ? 'No autenticado. Por favor inicie sesión.' 
                : $e->getMessage();

            return ApiResponse::error(
                $mensaje,
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

        // Red de seguridad para violaciones de restricción única no
        // atrapadas por una validación previa (ej. dos flujos distintos
        // intentando crear el mismo registro único a la vez). No sustituye
        // la validación preventiva en el punto de origen — solo evita que
        // el usuario vea un 500 genérico con SQL crudo si algo se escapa.
        $exceptions->render(function (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? null;

            if ($sqlState === '23505') { // unique_violation (Postgres)
                return ApiResponse::error(
                    'El registro ya existe — verifique que no esté duplicando datos que deben ser únicos (ej. cédula).',
                    null, 409
                );
            }

            return null; // no es una violación de unicidad: sigue al handler genérico
        });

        // Cualquier excepción HTTP no cubierta arriba (405, 400, 409, 429...)
        // debe responder con su código real, no caer al catch-all de 500.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            $exponerTrace = app()->environment('local') && config('app.debug');

            return ApiResponse::error(
                $e->getMessage() ?: 'Error en la solicitud.',
                $exponerTrace ? ['trace' => $e->getTraceAsString()] : null,
                $e->getStatusCode(),
            );
        });

        $exceptions->render(function (\Throwable $e) {
            $exponerTrace = app()->environment('local') && config('app.debug');

            if ($exponerTrace) {
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

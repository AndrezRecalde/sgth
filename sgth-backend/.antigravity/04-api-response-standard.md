# SGTH — Sistema de Gestión de Talento Humano
# Contexto del Agente — Archivo 04: Respuestas JSON Estándar

---

## CLASE ApiResponse — USAR SIEMPRE

Todos los controladores devuelven respuestas usando esta clase.
NUNCA usar `response()->json()` directamente en un controlador.

```php
<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ApiResponse
{
    /**
     * Respuesta exitosa genérica.
     */
    public static function ok(
        mixed $data = null,
        string $mensaje = 'Operación exitosa.',
        int $codigo = 200,
    ): JsonResponse {
        return response()->json([
            'exito'   => true,
            'mensaje' => $mensaje,
            'datos'   => $data,
            'meta'    => null,
        ], $codigo);
    }

    /**
     * Respuesta paginada.
     */
    public static function paginado(
        LengthAwarePaginator $data,
        string $mensaje = 'Consulta exitosa.',
    ): JsonResponse {
        return response()->json([
            'exito'   => true,
            'mensaje' => $mensaje,
            'datos'   => $data->items(),
            'meta'    => [
                'pagina_actual' => $data->currentPage(),
                'por_pagina'    => $data->perPage(),
                'total'         => $data->total(),
                'ultima_pagina' => $data->lastPage(),
                'desde'         => $data->firstItem(),
                'hasta'         => $data->lastItem(),
            ],
        ], 200);
    }

    /**
     * Recurso creado exitosamente.
     */
    public static function created(
        mixed $data = null,
        string $mensaje = 'Registro creado exitosamente.',
    ): JsonResponse {
        return self::ok($data, $mensaje, 201);
    }

    /**
     * Operación exitosa sin contenido (eliminaciones).
     */
    public static function noContent(
        string $mensaje = 'Registro eliminado exitosamente.',
    ): JsonResponse {
        return response()->json([
            'exito'   => true,
            'mensaje' => $mensaje,
            'datos'   => null,
            'meta'    => null,
        ], 200);
    }

    /**
     * Error genérico.
     */
    public static function error(
        string $mensaje = 'Ha ocurrido un error.',
        mixed $errores = null,
        int $codigo = 422,
    ): JsonResponse {
        return response()->json([
            'exito'   => false,
            'mensaje' => $mensaje,
            'datos'   => null,
            'errores' => $errores,
        ], $codigo);
    }

    /**
     * No autorizado (403).
     */
    public static function noAutorizado(
        string $mensaje = 'No tiene autorización para realizar esta acción.',
    ): JsonResponse {
        return self::error($mensaje, null, 403);
    }

    /**
     * No encontrado (404).
     */
    public static function noEncontrado(
        string $mensaje = 'El recurso solicitado no fue encontrado.',
    ): JsonResponse {
        return self::error($mensaje, null, 404);
    }

    /**
     * Error de servidor (500).
     */
    public static function errorServidor(
        string $mensaje = 'Error interno del servidor.',
    ): JsonResponse {
        return self::error($mensaje, null, 500);
    }
}
```

---

## ESTRUCTURA JSON DE RESPUESTAS

### Éxito simple (GET show, PUT update)
```json
{
  "exito": true,
  "mensaje": "Puesto actualizado exitosamente.",
  "datos": {
    "id": 1,
    "codigo": "ADM-001",
    "denominacion": "Técnico de Fiscalización",
    "grupo_ocupacional": "Técnico",
    "grado_rmu": 9,
    "rmu": "1212.00",
    "nivel": "No profesional",
    "estado": true,
    "creado_en": "2026-01-15T10:30:00-05:00"
  },
  "meta": null
}
```

### Creado exitosamente (POST store → 201)
```json
{
  "exito": true,
  "mensaje": "Permiso creado exitosamente.",
  "datos": {
    "id": 45,
    "tipo": "personal",
    "estado": "pendiente",
    "folio": "PER-2026-00045",
    "fecha": "2026-05-11",
    "hora_inicio": "10:00",
    "hora_fin": "12:00"
  },
  "meta": null
}
```

### Lista paginada (GET index)
```json
{
  "exito": true,
  "mensaje": "Consulta exitosa.",
  "datos": [
    { "id": 1, "denominacion": "Técnico de Fiscalización" },
    { "id": 2, "denominacion": "Analista Administrativo" }
  ],
  "meta": {
    "pagina_actual": 1,
    "por_pagina": 15,
    "total": 87,
    "ultima_pagina": 6,
    "desde": 1,
    "hasta": 15
  }
}
```

### Sin contenido (DELETE destroy)
```json
{
  "exito": true,
  "mensaje": "Puesto eliminado exitosamente.",
  "datos": null,
  "meta": null
}
```

### Error de validación (422)
```json
{
  "exito": false,
  "mensaje": "Los datos enviados no son válidos.",
  "datos": null,
  "errores": {
    "hora_fin": ["El permiso personal no puede exceder 4 horas por día."],
    "tipo": ["El tipo de permiso seleccionado no es válido."]
  }
}
```

### No autorizado (403)
```json
{
  "exito": false,
  "mensaje": "No tiene autorización para realizar esta acción.",
  "datos": null,
  "errores": null
}
```

### No encontrado (404)
```json
{
  "exito": false,
  "mensaje": "El recurso solicitado no fue encontrado.",
  "datos": null,
  "errores": null
}
```

---

## HANDLER GLOBAL DE EXCEPCIONES — bootstrap/app.php

```php
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
```

---

## EXCEPCIÓN DE REGLA DE NEGOCIO

```php
<?php

namespace App\Exceptions;

use RuntimeException;

final class ReglaNegocioException extends RuntimeException
{
    public function __construct(string $mensaje)
    {
        parent::__construct($mensaje);
    }
}

// Uso en Services:
throw new ReglaNegocioException(
    'El permiso personal no puede exceder 4 horas por día.'
);

throw new ReglaNegocioException(
    'No puede solicitar un nuevo viático con liquidaciones pendientes.'
);
```

---

## AUTENTICACIÓN — AuthController

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\CambiarContrasenaRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $resultado = $this->authService->login(
            $request->validated('usuario'),
            $request->validated('contrasena'),
        );

        return ApiResponse::ok($resultado, 'Inicio de sesión exitoso.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return ApiResponse::noContent('Sesión cerrada exitosamente.');
    }

    public function cambiarContrasenaInicial(CambiarContrasenaRequest $request): JsonResponse
    {
        $this->authService->cambiarContrasenaInicial(
            $request->user(),
            $request->validated('nueva_contrasena'),
        );

        return ApiResponse::ok(null, 'Contraseña actualizada exitosamente.');
    }

    public function perfil(Request $request): JsonResponse
    {
        return ApiResponse::ok($request->user()->load('roles'));
    }
}
```

---

## RUTAS API — routes/api.php

```php
<?php

use Illuminate\Support\Facades\Route;

// ── Rutas públicas (sin autenticación) ────────────────────────────
Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);
    });

    // Verificación pública de permiso por folio/QR (sin auth)
    Route::get('permisos/verificar/{folio}',
        [\App\Http\Controllers\Asistencia\PermisoController::class, 'verificar']
    );
});

// ── Rutas autenticadas ─────────────────────────────────────────────
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout']);
        Route::get('perfil', [\App\Http\Controllers\Auth\AuthController::class, 'perfil']);
        Route::post('cambiar-contrasena',
            [\App\Http\Controllers\Auth\AuthController::class, 'cambiarContrasenaInicial']
        );
    });

    // Módulo 01 — Estructura
    Route::prefix('estructura')
        ->middleware('role:admin-uath,asistente-uath,director,auditor')
        ->group(function () {
            Route::apiResource('unidades', \App\Http\Controllers\Estructura\UnidadAdministrativaController::class);
            Route::apiResource('puestos', \App\Http\Controllers\Estructura\PuestoController::class);
            Route::get('organigrama', [\App\Http\Controllers\Estructura\OrgangramaController::class, 'index']);
        });

    // Módulo 04 — Permisos (ejemplo de rutas con múltiples roles)
    Route::prefix('asistencia')->group(function () {
        Route::apiResource('permisos', \App\Http\Controllers\Asistencia\PermisoController::class);
        Route::post('permisos/{permiso}/anular',
            [\App\Http\Controllers\Asistencia\PermisoController::class, 'anular']
        )->middleware('role:jefe-unidad,director,admin-uath');
        Route::post('permisos/{folio}/confirmar-recepcion',
            [\App\Http\Controllers\Asistencia\RecepcionController::class, 'confirmar']
        )->middleware('role:recepcion');
        Route::post('permisos/{permiso}/validar-trabajo-social',
            [\App\Http\Controllers\Asistencia\TrabajoSocialController::class, 'validar']
        )->middleware('role:trabajo-social');
    });

    // Admin TI
    Route::prefix('admin')
        ->middleware('role:admin-ti')
        ->group(function () {
            Route::apiResource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class);
            Route::post('usuarios/{usuario}/restablecer-contrasena',
                [\App\Http\Controllers\Admin\UsuarioController::class, 'restablecerContrasena']
            );
            Route::apiResource('roles', \App\Http\Controllers\Admin\RolController::class);
        });
});
```


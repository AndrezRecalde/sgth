<?php

namespace App\Http\Controllers\Autoservicio;

use App\Contracts\Autoservicio\AutoservicioServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutoservicioController extends Controller
{
    public function __construct(private AutoservicioServiceInterface $autoservicioService) {}

    private function getServidorId(Request $request): int
    {
        return $request->user()->servidor->id ?? 0;
    }

    public function misPermisos(Request $request): JsonResponse
    {
        $permisos = $this->autoservicioService->obtenerMisPermisos(
            $this->getServidorId($request),
            $request->only(['estado', 'anio', 'per_page'])
        );
        return ApiResponse::ok($permisos, 'Mis permisos obtenidos.');
    }

    /**
     * Los compañeros de unidad, para elegir al jefe inmediato de un permiso
     * propio. Ver `AutoservicioService::obtenerCompanerosDeUnidad()`.
     */
    public function companerosDeUnidad(Request $request): JsonResponse
    {
        return ApiResponse::ok(
            $this->autoservicioService->obtenerCompanerosDeUnidad($this->getServidorId($request)),
            'Compañeros de unidad obtenidos.'
        );
    }

    public function misVacaciones(Request $request): JsonResponse
    {
        // Con el `0` de `getServidorId`, el saldo hacía `findOrFail(0)` y la
        // cuenta sin ficha recibía un 404 «La ruta solicitada no existe»
        // (PR 15).
        if (! $request->user()->servidor_id) {
            throw new ReglaNegocioException('Su cuenta no está vinculada a un servidor: no tiene vacaciones.');
        }

        $vacaciones = $this->autoservicioService->obtenerMisVacaciones($this->getServidorId($request));
        return ApiResponse::ok($vacaciones, 'Mis vacaciones obtenidas.');
    }

    public function miExpediente(Request $request): JsonResponse
    {
        $expediente = $this->autoservicioService->obtenerMiExpediente($this->getServidorId($request));
        return ApiResponse::ok($expediente, 'Mi expediente obtenido de forma segura.');
    }

    public function miHistoriaClinica(Request $request): JsonResponse
    {
        $historia = $this->autoservicioService->obtenerMiHistoriaClinicaBasica($this->getServidorId($request));
        return ApiResponse::ok($historia, 'Historia clínica básica obtenida (información clínica detallada no expuesta).');
    }
}

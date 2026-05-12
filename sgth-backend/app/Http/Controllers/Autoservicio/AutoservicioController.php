<?php

namespace App\Http\Controllers\Autoservicio;

use App\Contracts\Autoservicio\AutoservicioServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Autoservicio\StoreCitaMedicaAutoservicioRequest;
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
            $request->only(['estado', 'anio'])
        );
        return ApiResponse::ok($permisos, 'Mis permisos obtenidos.');
    }

    public function misVacaciones(Request $request): JsonResponse
    {
        $vacaciones = $this->autoservicioService->obtenerMisVacaciones($this->getServidorId($request));
        return ApiResponse::ok($vacaciones, 'Mis vacaciones obtenidas.');
    }

    public function misRolesPago(Request $request): JsonResponse
    {
        $roles = $this->autoservicioService->obtenerMisRolesPago($this->getServidorId($request));
        return ApiResponse::ok($roles, 'Mis roles de pago obtenidos.');
    }

    public function miExpediente(Request $request): JsonResponse
    {
        $expediente = $this->autoservicioService->obtenerMiExpediente($this->getServidorId($request));
        return ApiResponse::ok($expediente, 'Mi expediente obtenido de forma segura.');
    }

    public function misActividades(Request $request): JsonResponse
    {
        $actividades = $this->autoservicioService->obtenerMisActividades($this->getServidorId($request));
        return ApiResponse::ok($actividades, 'Mis actividades obtenidas.');
    }

    public function solicitarCita(StoreCitaMedicaAutoservicioRequest $request): JsonResponse
    {
        $resultado = $this->autoservicioService->solicitarCitaMedica(
            $this->getServidorId($request),
            $request->validated()
        );
        return ApiResponse::created($resultado, 'Cita médica agendada y permiso por enfermedad generado.');
    }

    public function miHistoriaClinica(Request $request): JsonResponse
    {
        $historia = $this->autoservicioService->obtenerMiHistoriaClinicaBasica($this->getServidorId($request));
        return ApiResponse::ok($historia, 'Historia clínica básica obtenida (información clínica detallada no expuesta).');
    }
}

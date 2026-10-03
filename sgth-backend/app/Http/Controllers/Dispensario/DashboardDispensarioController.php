<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\EstadisticasDispensarioServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Dispensario\PanoramaDispensarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DashboardDispensarioController extends Controller
{
    public function __construct(
        private readonly EstadisticasDispensarioServiceInterface $estadisticasService,
        private readonly PanoramaDispensarioService $panorama,
    ) {}

    /**
     * Las cifras del Dispensario de un período. Sin período, el mes en curso.
     */
    public function kpis(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);

        return ApiResponse::ok(
            $this->estadisticasService->obtenerKpis($desde, $hasta),
            'Estadísticas del dispensario generadas exitosamente'
        );
    }

    /**
     * El resto del Dispensario: flujo de pacientes, enfermería, reposos,
     * salud ocupacional y la tendencia de los últimos doce meses.
     */
    public function panorama(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);

        return ApiResponse::ok($this->panorama->resumen($desde, $hasta));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periodo(Request $request): array
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date', 'required_with:hasta'],
            'hasta' => ['nullable', 'date', 'required_with:desde', 'after_or_equal:desde'],
        ]);

        $desde = isset($datos['desde']) ? Carbon::parse($datos['desde']) : now()->startOfMonth();
        $hasta = isset($datos['hasta']) ? Carbon::parse($datos['hasta']) : now()->endOfMonth();

        // Un tope de un año: un rango de diez años es una consulta legítima
        // para el sistema y un tablero que nadie puede leer.
        if ($desde->diffInDays($hasta) > 366) {
            throw ValidationException::withMessages([
                'hasta' => 'El período del tablero no puede pasar de un año.',
            ]);
        }

        return [$desde, $hasta];
    }
}

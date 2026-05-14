<?php

namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use Illuminate\Http\Request;

class AuditoriaInventarioController extends Controller
{
    public function __construct(private readonly InventarioTiServiceInterface $service)
    {
    }

    public function escanear(Request $request)
    {
        $validated = $request->validate([
            'codigo_qr' => 'required_without:numero_serie|string',
            'numero_serie' => 'required_without:codigo_qr|string'
        ]);

        // Delegamos al servicio la búsqueda del bien y la carga de su historial
        // (asignaciones, mantenimientos, etc.)
        $ficha = $this->service->obtenerFichaTecnicaCompleta($validated);

        return ApiResponse::ok($ficha, 'Ficha técnica del bien informático.');
    }

    public function registrarAuditoria(Request $request)
    {
        $validated = $request->validate([
            'bien_informatico_id' => 'required|exists:bienes_informaticos,id',
            'estado_fisico' => 'required|string',
            'observaciones' => 'nullable|string'
        ]);

        // Delegamos al servicio el registro de la auditoría física
        $auditoria = $this->service->registrarAuditoriaFisica($validated);

        return ApiResponse::created($auditoria, 'Auditoría física registrada exitosamente.');
    }
}

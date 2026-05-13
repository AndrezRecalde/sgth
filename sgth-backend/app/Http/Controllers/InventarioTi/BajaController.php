<?php

namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\BienInformatico;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use Illuminate\Http\Request;

class BajaController extends Controller
{
    public function __construct(private readonly InventarioTiServiceInterface $service)
    {
    }

    public function index()
    {
        // Lista los bienes que están marcados con estado dado_de_baja
        $bienes = BienInformatico::where('estado', 'dado_de_baja')->latest()->get();
        return ApiResponse::ok($bienes, 'Bienes informáticos en proceso de baja.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bien_informatico_id' => 'required|exists:bienes_informaticos,id',
            'motivo' => 'required|string',
            'documento_respaldo' => 'nullable|string'
        ]);

        // Delegamos al servicio toda la lógica:
        // 1. Cambiar estado a dado_de_baja
        // 2. Generar acta PDF con PdfService
        // 3. Archivar acta en SGD
        $resultado = $this->service->procesarBaja($validated);

        return ApiResponse::created($resultado, 'Proceso de baja iniciado. Acta generada y archivada.');
    }
}

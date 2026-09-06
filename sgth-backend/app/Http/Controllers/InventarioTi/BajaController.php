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
        // La columna es `estado_operativo`; `estado` no existe en esta tabla y
        // la consulta reventaba con un error de Postgres.
        $bienes = BienInformatico::with(['tipo', 'marca'])
            ->where('estado_operativo', 'dado_de_baja')
            ->latest()
            ->get();

        return ApiResponse::ok($bienes, 'Bienes informáticos dados de baja.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bien_informatico_id' => 'required|exists:bienes_informaticos,id',
            'motivo' => 'required|string',
            'documento_respaldo' => 'nullable|string'
        ]);

        // El servicio marca el bien como dado de baja. El acta en PDF y su
        // archivo en el SGD todavía no están construidos, así que la respuesta
        // ya no dice que se generaron: lo anterior devolvía una ruta de PDF y
        // una referencia de SGD inventadas.
        $resultado = $this->service->procesarBaja($validated);

        return ApiResponse::created(
            $resultado,
            'Bien dado de baja. El acta queda pendiente de generar.'
        );
    }
}

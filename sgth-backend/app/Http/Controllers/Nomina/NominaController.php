<?php

namespace App\Http\Controllers\Nomina;

use App\Contracts\Nomina\NominaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nomina\CerrarNominaRequest;
use App\Http\Requests\Nomina\StoreNominaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Nomina\Nomina;

class NominaController extends Controller
{
    public function __construct(
        private NominaServiceInterface $nominaService
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Nomina::class);
        $nominas = Nomina::orderBy('periodo', 'desc')->get();

        return ApiResponse::ok($nominas, 'Listado de nóminas');
    }

    public function show(int $id)
    {
        $nomina = Nomina::with(['detalles', 'cerradoPor'])->findOrFail($id);
        $this->authorize('view', $nomina);

        return ApiResponse::ok($nomina, 'Detalle de nómina');
    }

    public function calcular(StoreNominaRequest $request)
    {
        $this->authorize('create', Nomina::class);
        
        $nomina = $this->nominaService->calcularNomina($request->validated('periodo'));
        
        return ApiResponse::ok($nomina, 'Nómina calculada exitosamente en borrador.');
    }

    public function cerrar(CerrarNominaRequest $request, int $id)
    {
        $nomina = Nomina::findOrFail($id);
        $this->authorize('cerrar', $nomina);
        
        $nominaCerrada = $this->nominaService->cerrarNomina($id, $request->user()->id);
        
        return ApiResponse::ok($nominaCerrada, 'La nómina ha sido cerrada y los procesos de ERP y correos se están ejecutando en segundo plano.');
    }
}

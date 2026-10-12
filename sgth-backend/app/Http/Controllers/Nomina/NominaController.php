<?php

namespace App\Http\Controllers\Nomina;

use App\Contracts\Nomina\NominaServiceInterface;
use App\Exports\NominaComparacionExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nomina\CerrarNominaRequest;
use App\Http\Requests\Nomina\StoreNominaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Nomina\Nomina;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        $nomina = Nomina::with([
            'detalles.concepto',
            'rolesPago.servidor',
            'cerradoPor'
        ])->findOrFail($id);
        $this->authorize('view', $nomina);

        return ApiResponse::ok($nomina, 'Detalle de nómina');
    }

    public function calcular(StoreNominaRequest $request)
    {
        $this->authorize('create', Nomina::class);

        // La regla de negocio llega como 422 por el manejador de excepciones.
        $nomina = $this->nominaService->calcularNomina($request->validated('periodo'));

        return ApiResponse::ok(
            $nomina,
            $nomina->referencial
                ? 'Nómina calculada en borrador. Es referencial: sirve para compararla con la de Financiero.'
                : 'Nómina calculada exitosamente en borrador.',
        );
    }

    /**
     * La nómina por servidor, en Excel, para compararla con la de Financiero
     * en la corrida en paralelo (fase 7.3c).
     */
    public function excel(int $id): BinaryFileResponse
    {
        $nomina = Nomina::findOrFail($id);
        $this->authorize('view', $nomina);

        return Excel::download(new NominaComparacionExport($nomina), "nomina_{$nomina->periodo}.xlsx");
    }

    public function cerrar(CerrarNominaRequest $request, int $id)
    {
        $nomina = Nomina::findOrFail($id);
        $this->authorize('cerrar', $nomina);

        $nominaCerrada = $this->nominaService->cerrarNomina($id, $request->user()->id);

        return ApiResponse::ok($nominaCerrada, $nominaCerrada->referencial
            ? 'La nómina quedó cerrada para compararla con la de Financiero. Es referencial: no sale al ERP ni a los servidores.'
            : 'La nómina ha sido cerrada y los procesos de ERP y correos se están ejecutando en segundo plano.');
    }
}

<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\AdquisicionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreAdquisicionRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class AdquisicionController extends Controller
{
    public function __construct(
        private readonly AdquisicionServiceInterface $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $adquisiciones = $this->service->listar($request->all());

        return ApiResponse::ok(
            $adquisiciones, 'Listado de adquisiciones.'
        );
    }

    public function store(
        StoreAdquisicionRequest $request
    ): JsonResponse {
        $validado = $request->validated();
        $items    = $validado['items'];
        unset($validado['items']);

        $adquisicion = $this->service->registrar(
            $validado, $items, $request->user()->id
        );

        return ApiResponse::created(
            $adquisicion, 'Adquisición registrada correctamente.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $adquisicion = $this->service->obtener($id);

        return ApiResponse::ok($adquisicion);
    }

    public function anular(
        Request $request,
        int $id
    ): JsonResponse {
        $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        $adquisicion = $this->service->anular(
            $id,
            $request->string('motivo_anulacion')->value(),
            $request->user()->id
        );

        return ApiResponse::ok(
            $adquisicion, 'Adquisición anulada correctamente.'
        );
    }

    public function subirDocumento(
        Request $request,
        int $id
    ): JsonResponse {
        $request->validate([
            'documento' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        // Disco privado: una factura o un acta de donación no debe quedar
        // servida por URL a cualquiera que la adivine. Se entrega por el
        // endpoint de abajo, que pasa por la sesión.
        $ruta = $request->file('documento')->store(
            'adquisiciones', 'local'
        );

        $adquisicion = $this->service->subirDocumento($id, $ruta);

        return ApiResponse::ok(
            $adquisicion, 'Documento subido correctamente.'
        );
    }

    /**
     * Entrega el respaldo para verlo en el navegador. Hasta ahora el archivo se
     * subía y no había forma de recuperarlo: la pantalla solo mostraba una
     * insignia diciendo que existía.
     */
    public function verDocumento(int $id): SymfonyResponse
    {
        $adquisicion = $this->service->obtener($id);

        if (! $adquisicion->documento_respaldo) {
            return ApiResponse::error(
                'Esta adquisición no tiene documento de respaldo.', null, 404
            );
        }

        if (! Storage::disk('local')->exists($adquisicion->documento_respaldo)) {
            return ApiResponse::error(
                'El documento de respaldo ya no está disponible.', null, 404
            );
        }

        $extension = pathinfo(
            $adquisicion->documento_respaldo, PATHINFO_EXTENSION
        );

        return response(
            Storage::disk('local')->get($adquisicion->documento_respaldo),
            200,
            [
                'Content-Type' => Storage::disk('local')
                    ->mimeType($adquisicion->documento_respaldo),
                'Content-Disposition' => 'inline; filename="respaldo-'
                    . $adquisicion->folio . '.' . $extension . '"',
            ]
        );
    }
}

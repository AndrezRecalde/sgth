<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\AdquisicionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreAdquisicionRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        $ruta = $request->file('documento')->store(
            'adquisiciones', 'public'
        );

        $adquisicion = $this->service->subirDocumento($id, $ruta);

        return ApiResponse::ok(
            $adquisicion, 'Documento subido correctamente.'
        );
    }
}

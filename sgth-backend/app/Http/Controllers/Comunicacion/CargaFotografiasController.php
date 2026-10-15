<?php

namespace App\Http\Controllers\Comunicacion;

use App\Contracts\Comunicacion\CargaFotografiasServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicacion\CargarFotografiasRequest;
use App\Http\Requests\Comunicacion\SimularCargaFotografiasRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * La carga masiva de fotos con la cédula en el nombre del archivo
 * (docs/diseno-comunicacion-social.md, 5.1). Primero se simula con los
 * nombres; después se suben las fotos en tandas.
 */
final class CargaFotografiasController extends Controller
{
    public function __construct(private readonly CargaFotografiasServiceInterface $carga) {}

    public function simular(SimularCargaFotografiasRequest $request): JsonResponse
    {
        $informe = $this->carga->simular($request->validated('archivos'), $request->boolean('reemplazar'));

        return ApiResponse::ok($informe, sprintf(
            'De %d archivos, se cargarían %d y se omitirían %d.',
            $informe['resumen']['total'], $informe['resumen']['se_cargan'], $informe['resumen']['se_omiten'],
        ));
    }

    public function cargar(CargarFotografiasRequest $request): JsonResponse
    {
        $informe = $this->carga->cargar($request->file('fotos'), $request->boolean('reemplazar'), $request->user());

        return ApiResponse::ok($informe, sprintf(
            'De %d archivos, se cargaron %d y se omitieron %d.',
            $informe['resumen']['total'], $informe['resumen']['se_cargan'], $informe['resumen']['se_omiten'],
        ));
    }
}

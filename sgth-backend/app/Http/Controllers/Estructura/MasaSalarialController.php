<?php

namespace App\Http\Controllers\Estructura;

use App\Exports\MasaSalarial\MasaSalarialExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\MasaSalarialRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Estructura\MasaSalarialService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MasaSalarialController extends Controller
{
    public function __construct(private MasaSalarialService $masa)
    {
    }

    /**
     * La masa salarial referencial a una fecha de corte (por omisión, hoy): la
     * suma de las remuneraciones registradas en los vínculos vigentes, por
     * tipo de nombramiento, régimen y unidad, con su detalle. No es lo pagado.
     */
    public function resumen(MasaSalarialRequest $request): JsonResponse
    {
        return ApiResponse::ok(
            $this->masa->al($request->corte(), $request->filtros()),
            'Masa salarial referencial.'
        );
    }

    /**
     * Lo mismo en Excel: el resumen con el aviso y la fecha de corte, la
     * matriz de unidad por régimen y el detalle de cada vínculo.
     */
    public function excel(MasaSalarialRequest $request): BinaryFileResponse
    {
        $corte = $request->corte();

        return Excel::download(
            new MasaSalarialExport($this->masa->al($corte, $request->filtros())),
            MasaSalarialExport::nombreDelArchivo($corte->toDateString()),
        );
    }
}

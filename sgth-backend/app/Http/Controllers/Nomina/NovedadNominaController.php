<?php

namespace App\Http\Controllers\Nomina;

use App\Exports\NovedadesNominaExport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Nomina\MesDeNomina;
use App\Services\Nomina\NovedadesNominaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Las novedades para nómina del mes (fase 7.3a): Talento Humano las revisa y
 * las entrega; Financiero, que las aplica en su sistema, las consulta y las
 * descarga.
 */
class NovedadNominaController extends Controller
{
    public function __construct(
        private NovedadesNominaService $novedades,
    ) {
    }

    /**
     * Las novedades de un mes: derivadas de los actos si está abierto, o como
     * se entregaron, con lo que cambió después.
     */
    public function index(Request $request): JsonResponse
    {
        $mes = $this->mes($request);

        return ApiResponse::ok($this->novedades->delMes($mes), "Novedades para nómina de {$mes->etiqueta()}.");
    }

    /** Las novedades y los ajustes del mes, en Excel. */
    public function excel(Request $request): BinaryFileResponse
    {
        $mes = $this->mes($request);

        return Excel::download(
            new NovedadesNominaExport($this->novedades->filasDelExcel($mes)),
            "novedades_nomina_{$mes->periodo}.xlsx",
        );
    }

    /** Congela el mes y se lo envía al jefe de Gestión Financiera. */
    public function entregar(Request $request): JsonResponse
    {
        $request->validate(['periodo' => ['required', 'date_format:Y-m']]);
        $mes = MesDeNomina::de($request->string('periodo')->toString());

        $entrega = $this->novedades->entregar($mes, $request->user());

        return ApiResponse::ok(
            $this->novedades->delMes($mes),
            "Se entregaron las novedades de {$mes->etiqueta()} a Financiero.",
            meta: ['aviso_financiero' => $entrega->aviso],
        );
    }

    private function mes(Request $request): MesDeNomina
    {
        $request->validate(['periodo' => ['nullable', 'date_format:Y-m']]);

        return $request->filled('periodo')
            ? MesDeNomina::de($request->string('periodo')->toString())
            : MesDeNomina::actual();
    }
}

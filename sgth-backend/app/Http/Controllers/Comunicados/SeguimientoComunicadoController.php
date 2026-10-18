<?php

namespace App\Http\Controllers\Comunicados;

use App\Contracts\Comunicados\SeguimientoComunicadoServiceInterface;
use App\Exports\LecturasPendientesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicados\ListarLecturasComunicadoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Comunicados\Comunicado;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El seguimiento de lecturas (docs/diseno-noticias-comunicados.md, 6.4): las
 * cifras, la tabla de destinatarios y el Excel de quién falta. Lo pide
 * `ver-lecturas-comunicados` en la ruta; un borrador responde 422.
 */
final class SeguimientoComunicadoController extends Controller
{
    public function __construct(private readonly SeguimientoComunicadoServiceInterface $seguimiento) {}

    public function resumen(Comunicado $comunicado): JsonResponse
    {
        return ApiResponse::ok($this->seguimiento->resumen($comunicado), 'Lecturas de la publicación.');
    }

    public function index(ListarLecturasComunicadoRequest $request, Comunicado $comunicado): JsonResponse
    {
        $pagina = $this->seguimiento->listar($comunicado, $request->validated());

        return ApiResponse::paginadoDe($pagina, $pagina->items(), 'Destinatarios de la publicación.');
    }

    public function excel(ListarLecturasComunicadoRequest $request, Comunicado $comunicado): BinaryFileResponse
    {
        $unidadId = $request->validated('unidad_administrativa_id');

        return Excel::download(
            new LecturasPendientesExport($this->seguimiento->pendientes($comunicado, $unidadId ? (int) $unidadId : null)),
            'pendientes_comunicado_'.$comunicado->id.'_'.now()->format('Ymd_His').'.xlsx',
        );
    }
}

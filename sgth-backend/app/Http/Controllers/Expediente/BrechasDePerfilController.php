<?php

namespace App\Http\Controllers\Expediente;

use App\Exports\BrechasDePerfilExport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Expediente\BrechasDePerfilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Las brechas de perfil (docs/diseno-manual-de-puestos.md, P7, PR 17): cada
 * servidor frente al perfil de su puesto. Muestra el historial académico de
 * todos, así que pide `ver-expediente-todos` (en la ruta).
 */
final class BrechasDePerfilController extends Controller
{
    public function __construct(private readonly BrechasDePerfilService $brechas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok($this->brechas->informe($this->filtros($request)), 'Brechas de perfil.');
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $informe = $this->brechas->informe($this->filtros($request));

        return Excel::download(
            new BrechasDePerfilExport(collect($informe['filas'])),
            'brechas_de_perfil_'.now()->format('Ymd_His').'.xlsx',
        );
    }

    /** @return array{unidad_administrativa_id: int|null} */
    private function filtros(Request $request): array
    {
        $datos = $request->validate([
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
        ]);

        return ['unidad_administrativa_id' => isset($datos['unidad_administrativa_id']) ? (int) $datos['unidad_administrativa_id'] : null];
    }
}

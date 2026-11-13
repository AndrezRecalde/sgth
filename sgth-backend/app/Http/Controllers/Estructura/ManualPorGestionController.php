<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Models\Estructura\UnidadAdministrativa;
use App\Services\Estructura\ManualPorGestionPdfService;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * El Manual de Puestos de una gestión en PDF (docs/diseno-manual-de-puestos.md,
 * P5, PR 14). Lo imprime quien ve la estructura (`ver-estructura`, en la ruta),
 * como la ficha suelta de cada puesto.
 */
final class ManualPorGestionController extends Controller
{
    /** El organigrama tiene tres niveles: la institución, las gestiones y sus subprocesos. */
    private const NIVEL_GESTION = 2;

    public function __invoke(int $unidadId, ManualPorGestionPdfService $manual): Response
    {
        $gestion = UnidadAdministrativa::findOrFail($unidadId);

        if ((int) $gestion->nivel !== self::NIVEL_GESTION) {
            throw ValidationException::withMessages([
                'unidad' => 'El manual se imprime por gestión: elija una unidad del segundo nivel del organigrama.',
            ]);
        }
        if ($manual->puestos($gestion)->isEmpty()) {
            throw ValidationException::withMessages([
                'unidad' => "{$gestion->nombre} no tiene puestos de plantilla activos.",
            ]);
        }

        $pdf = $manual->generar($gestion);

        return response($pdf['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['filename'].'"',
        ]);
    }
}

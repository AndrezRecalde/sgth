<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\CargarFichaManualRequest;
use App\Http\Requests\Estructura\GuardarEquivalenciaManualRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\FichaManualPuestos;
use App\Models\Estructura\Puesto;
use App\Services\Estructura\Manual\RevisionDelManualService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La revisión de las fichas del Manual de Puestos 2018-2019
 * (docs/diseno-manual-de-puestos.md, P4; PR 12): «Manual» en Puestos y
 * cargos. Todo con `gestionar-puestos`, que pide la ruta: es quien edita el
 * perfil (D6).
 */
class ManualDePuestosController extends Controller
{
    public function __construct(private readonly RevisionDelManualService $revision)
    {
    }

    /** Las fichas con su estado y el puesto sugerido o cargado. */
    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->revision->fichas(), 'Fichas del Manual de Puestos.');
    }

    /** Una ficha con su propuesta de perfil y los puestos de su unidad. */
    public function show(int $fichaId): JsonResponse
    {
        return ApiResponse::ok(
            $this->revision->ficha(FichaManualPuestos::findOrFail($fichaId)),
            'Ficha del Manual de Puestos.',
        );
    }

    /** Carga la ficha como perfil del puesto: reemplaza lo que tenía. */
    public function cargar(CargarFichaManualRequest $request, int $fichaId): JsonResponse
    {
        $ficha  = FichaManualPuestos::findOrFail($fichaId);
        $puesto = Puesto::findOrFail((int) $request->validated('puesto_id'));

        $cargado = $this->revision->cargar($ficha, $puesto, $request->user());

        return ApiResponse::ok(
            $this->revision->ficha($ficha->refresh()),
            sprintf(
                'Perfil cargado: %d %s, %d %s y %d %s.',
                $cargado['requisitos'], $cargado['requisitos'] === 1 ? 'alternativa de instrucción' : 'alternativas de instrucción',
                $cargado['actividades'], $cargado['actividades'] === 1 ? 'actividad' : 'actividades',
                $cargado['competencias'], $cargado['competencias'] === 1 ? 'competencia' : 'competencias',
            ),
        );
    }

    /** Quita el emparejamiento; el perfil cargado se queda en el puesto. */
    public function desvincular(int $fichaId): JsonResponse
    {
        $ficha = FichaManualPuestos::findOrFail($fichaId);
        $this->revision->desvincular($ficha);

        return ApiResponse::ok(
            $this->revision->ficha($ficha->refresh()),
            'La ficha quedó sin puesto. El perfil que se cargó sigue en el puesto.',
        );
    }

    /** Las gestiones del manual y la unidad de hoy de cada una. */
    public function equivalencias(): JsonResponse
    {
        return ApiResponse::ok($this->revision->equivalencias(), 'Unidades del Manual de Puestos.');
    }

    public function guardarEquivalencia(GuardarEquivalenciaManualRequest $request): JsonResponse
    {
        $unidad = $request->validated('unidad_administrativa_id');
        $this->revision->guardarEquivalencia((string) $request->validated('gestion'), $unidad === null ? null : (int) $unidad);

        return ApiResponse::ok(
            $this->revision->equivalencias(),
            $unidad === null ? 'La gestión vuelve a la unidad sugerida.' : 'Unidad guardada.',
        );
    }
}

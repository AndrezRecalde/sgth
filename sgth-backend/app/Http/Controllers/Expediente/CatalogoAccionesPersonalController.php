<?php

namespace App\Http\Controllers\Expediente;

use App\Enums\ClaseAccionPersonal;
use App\Enums\FamiliaAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\SupuestoProvisional;
use App\Http\Controllers\Controller;
use App\Http\Resources\Expediente\ClaseAccionPersonalResource;
use App\Http\Resources\Expediente\FamiliaAccionPersonalResource;
use App\Http\Resources\Expediente\InstrumentoActoResource;
use App\Http\Resources\Expediente\SupuestoProvisionalResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * El catálogo de acciones de personal: qué clases hay, cómo se agrupan, a qué
 * nombramientos aplican y qué pide el formulario en cada una.
 *
 * Es la única fuente de esas reglas para la pantalla. Hasta la fase 1.1 del
 * rediseño, `taxonomiaAccionPersonal.ts` las copiaba a mano y había que
 * mantener dos listas iguales en dos lenguajes.
 *
 * No depende del servidor: la pantalla cruza `nombramientos_elegibles` con el
 * nombramiento vigente que ya tiene en la mano. El backend vuelve a validarlo
 * al registrar.
 */
class CatalogoAccionesPersonalController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::ok([
            'familias' => FamiliaAccionPersonalResource::collection(FamiliaAccionPersonal::cases()),
            'clases'   => ClaseAccionPersonalResource::collection(ClaseAccionPersonal::cases()),
            // Con qué documento se formaliza cada acto (fase 2.7).
            'instrumentos' => InstrumentoActoResource::collection(InstrumentoActo::cases()),
            // Para qué se da un provisional (LOSEP 17 b; fase 3.2). El del ascenso no
            // se elige: lo pone el ascenso (fase 3.3).
            'supuestos_provisional' => SupuestoProvisionalResource::collection(SupuestoProvisional::delIngreso()),
        ], 'Catálogo de acciones de personal.');
    }
}

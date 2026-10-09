<?php

namespace App\Http\Controllers\Expediente;

use App\Enums\ClaseAccionPersonal;
use App\Enums\FamiliaAccionPersonal;
use App\Http\Controllers\Controller;
use App\Http\Resources\Expediente\ClaseAccionPersonalResource;
use App\Http\Resources\Expediente\FamiliaAccionPersonalResource;
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
        ], 'Catálogo de acciones de personal.');
    }
}

<?php

namespace App\Http\Controllers\Sso;

use App\Enums\TipoPermiso;
use App\Http\Controllers\Asistencia\ConsolidadoPermisoController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El ausentismo por enfermedad de Riesgos laborales
 * (docs/plan-roles-y-accesos.md, PR 12).
 *
 * Es el consolidado de permisos de Asistencia con el tipo fijo en
 * «enfermedad», y por eso lo hereda entero: la exportación y la validación del
 * rango no se copian. Lo que cambia es quién entra. El de Asistencia pide
 * `ver-permisos-todos`, que abre los permisos de toda la institución; este va
 * con los permisos de SSO, y solo da las ausencias por enfermedad, que son el
 * indicador reactivo del módulo.
 */
class AusentismoSsoController extends ConsolidadoPermisoController
{
    /** El consolidado del período, solo de permisos por enfermedad. */
    public function index(Request $request): JsonResponse
    {
        return $this->consolidado($request);
    }

    /**
     * El tipo no lo elige quien pide: con otro, este endpoint serviría
     * cualquier consolidado a quien no tiene `ver-permisos-todos`.
     */
    protected function filtros(Request $request): array
    {
        return [...parent::filtros($request), 'tipo' => TipoPermiso::ENFERMEDAD->value];
    }
}

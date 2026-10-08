<?php

namespace App\Http\Controllers\Autoservicio;

use App\Http\Controllers\Controller;
use App\Http\Resources\Autoservicio\MiCertificadoMedicoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\CertificadoMedico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los reposos médicos del propio servidor, para «Mis permisos» del portal.
 *
 * Desde el 2026-10-08 el reposo del dispensario es un certificado y no un
 * permiso, así que dejó de salir en la lista de permisos. Aquí ve sus días y
 * en qué quedó (pendiente de TH, en Sirha7, cargado a mano o anulado), sin el
 * diagnóstico (decisión del 2026-10-08).
 *
 * Solo los suyos: la consulta va por el servidor de la sesión, sin parámetro
 * que lo cambie.
 */
class MisCertificadosMedicosController extends Controller
{
    private const POR_PAGINA_MAX = 100;

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'anio'     => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $servidorId = $request->user()->servidor_id ?? 0;
        $porPagina = min(max($request->integer('per_page', 10), 1), self::POR_PAGINA_MAX);

        $pagina = CertificadoMedico::query()
            ->where('servidor_id', $servidorId)
            ->when($request->filled('anio'), fn ($q) => $q->whereYear('fecha_inicio', $request->integer('anio')))
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->paginate($porPagina);

        $pagina->setCollection(
            $pagina->getCollection()->map(fn (CertificadoMedico $c) => (new MiCertificadoMedicoResource($c))->resolve($request))
        );

        return ApiResponse::ok($pagina, 'Mis certificados médicos.');
    }
}

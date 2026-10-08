<?php

namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Concerns\RespondeSinBiometrico;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\AprobarCertificadoSinSirha7Request;
use App\Http\Requests\Asistencia\AprobarPermisoSirha7Request;
use App\Http\Resources\Asistencia\CertificadoAprobacionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\CertificadoMedico;
use App\Services\Asistencia\AprobacionCertificadoSirha7Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Los certificados médicos del dispensario, para que Talento Humano y Trabajo
 * Social los aprueben y los registren en Sirha7 (decisión del 2026-10-08).
 *
 * Las reglas viven en `AprobacionCertificadoSirha7Service`; aquí solo se
 * autoriza y se responde. Los tipos de Sirha7 salen de la misma ruta que los
 * de los permisos (`permisos/sirha7/tipos`).
 */
class CertificadoAprobacionController extends Controller
{
    use RespondeSinBiometrico;

    public function __construct(private AprobacionCertificadoSirha7Service $aprobacion) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('verParaAprobar', CertificadoMedico::class);

        // Una fecha mal escrita llegaba a `whereDate()` y Postgres respondía 500.
        $filtros = $request->validate([
            'estado'                   => ['nullable', Rule::in(['pendiente', 'aprobado', 'anulado'])],
            'folio'                    => ['nullable', 'string', 'max:30'],
            'servidor_id'              => ['nullable', 'integer'],
            'unidad_administrativa_id' => ['nullable', 'integer'],
            'fecha_desde'              => ['nullable', 'date'],
            'fecha_hasta'              => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page'                 => ['nullable', 'integer'],
        ]);

        $pagina = $this->aprobacion->listar($filtros);
        $pagina->setCollection(
            $pagina->getCollection()->map(fn (CertificadoMedico $c) => (new CertificadoAprobacionResource($c))->resolve($request))
        );

        return ApiResponse::ok($pagina, 'Certificados médicos.');
    }

    public function previa(int $id): JsonResponse
    {
        $certificado = CertificadoMedico::findOrFail($id);
        $this->authorize('aprobar', $certificado);

        return ApiResponse::ok($this->aprobacion->previa($certificado), 'Lo que se registrará en Sirha7.');
    }

    public function aprobar(int $id, AprobarPermisoSirha7Request $request): JsonResponse
    {
        $this->authorize('aprobar', CertificadoMedico::findOrFail($id));

        $usuario = $request->user();

        return $this->conBiometrico(fn () => ApiResponse::ok(
            $this->respuesta($this->aprobacion->aprobar(
                $id,
                $request->integer('leave_id'),
                $usuario->id,
                $usuario->servidor_id,
            ), $request),
            'Certificado aprobado y registrado en Sirha7.'
        ), 'No se pudo conectar al sistema biométrico. El certificado no cambió; inténtelo de nuevo.');
    }

    public function aprobarSinSirha7(int $id, AprobarCertificadoSinSirha7Request $request): JsonResponse
    {
        $this->authorize('aprobar', CertificadoMedico::findOrFail($id));

        $usuario = $request->user();

        return ApiResponse::ok(
            $this->respuesta($this->aprobacion->aprobarSinSirha7(
                $id,
                $request->string('nota')->trim()->toString(),
                $usuario->id,
                $usuario->servidor_id,
            ), $request),
            'Certificado aprobado sin registrarlo en Sirha7.'
        );
    }

    private function respuesta(CertificadoMedico $certificado, Request $request): array
    {
        $certificado->load(['servidor.unidadAdministrativa', 'emisor.servidor', 'aprobadoPor.servidor']);

        return (new CertificadoAprobacionResource($certificado))->resolve($request);
    }
}

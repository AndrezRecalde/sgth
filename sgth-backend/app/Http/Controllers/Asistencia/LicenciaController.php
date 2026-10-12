<?php

namespace App\Http\Controllers\Asistencia;

use App\Enums\CausaLicencia;
use App\Http\Controllers\Concerns\RespondeSinBiometrico;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\AprobarCertificadoSinSirha7Request;
use App\Http\Requests\Asistencia\AprobarPermisoSirha7Request;
use App\Http\Requests\Asistencia\StoreLicenciaRequest;
use App\Http\Resources\Asistencia\LicenciaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\Licencia;
use App\Policies\Asistencia\LicenciaPolicy;
use App\Services\Asistencia\AprobacionLicenciaSirha7Service;
use App\Services\Asistencia\CatalogoDeLicenciasService;
use App\Services\Asistencia\LicenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Las licencias con remuneración, fuera de las vacaciones (fase 5.1). Las
 * reglas viven en `LicenciaService` y `AprobacionLicenciaSirha7Service`; aquí
 * solo se autoriza y se responde. Los tipos de Sirha7 salen de la misma ruta
 * que los de los permisos (`permisos/sirha7/tipos`).
 */
class LicenciaController extends Controller
{
    use RespondeSinBiometrico;

    public function __construct(
        private LicenciaService $licencias,
        private AprobacionLicenciaSirha7Service $aprobacion,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('verAny', Licencia::class);

        $filtros = $request->validate([
            'estado'                   => ['nullable', Rule::in(['pendiente', 'aprobada', 'anulada'])],
            'causa'                    => ['nullable', Rule::enum(CausaLicencia::class)],
            'folio'                    => ['nullable', 'string', 'max:30'],
            'servidor_id'              => ['nullable', 'integer'],
            'unidad_administrativa_id' => ['nullable', 'integer'],
            'fecha_desde'              => ['nullable', 'date'],
            'fecha_hasta'              => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page'                 => ['nullable', 'integer'],
        ]);

        $causas = LicenciaPolicy::causasVisibles($request->user());
        if ($causas !== null) {
            $filtros['causas'] = $causas;
        }

        $pagina = $this->licencias->listar($filtros);
        $pagina->setCollection(
            $pagina->getCollection()->map(fn (Licencia $l) => (new LicenciaResource($l))->resolve($request))
        );

        return ApiResponse::ok($pagina, 'Licencias.');
    }

    /**
     * Las causas con su tope, sus casos y su base legal, para el formulario.
     * Con `servidor_id`, las suyas en esa `fecha`: a un obrero, con lo que
     * amplía o añade el contrato colectivo que rige (fase 5.3); `meta` dice
     * qué contrato, o por qué no se le aplica.
     */
    public function causas(Request $request, CatalogoDeLicenciasService $catalogo): JsonResponse
    {
        $this->authorize('verAny', Licencia::class);

        $datos = $request->validate([
            'servidor_id' => ['nullable', 'integer', 'exists:servidores,id'],
            'fecha'       => ['nullable', 'date'],
        ], [], ['servidor_id' => 'servidor', 'fecha' => 'fecha de inicio']);

        $resultado = $catalogo->catalogo(
            isset($datos['servidor_id']) ? (int) $datos['servidor_id'] : null,
            $datos['fecha'] ?? null,
        );

        return ApiResponse::ok($resultado['causas'], 'Causas de licencia.', 200, $resultado['meta']);
    }

    public function store(StoreLicenciaRequest $request): JsonResponse
    {
        $this->authorize('registrar', Licencia::class);

        $usuario = $request->user();
        $licencia = $this->licencias->registrar(
            $request->safe()->except('archivo'),
            $request->file('archivo'),
            $usuario->id,
            $usuario->servidor_id,
        );

        return ApiResponse::created($this->respuesta($licencia, $request), 'Licencia registrada: queda pendiente de aprobación.');
    }

    public function previa(int $id): JsonResponse
    {
        $licencia = Licencia::findOrFail($id);
        $this->authorize('aprobar', $licencia);

        return ApiResponse::ok($this->aprobacion->previa($licencia), 'Lo que se registrará en Sirha7.');
    }

    public function aprobar(int $id, AprobarPermisoSirha7Request $request): JsonResponse
    {
        $this->authorize('aprobar', Licencia::findOrFail($id));

        $usuario = $request->user();

        return $this->conBiometrico(fn () => ApiResponse::ok(
            $this->respuesta($this->aprobacion->aprobar(
                $id,
                $request->integer('leave_id'),
                $usuario->id,
                $usuario->servidor_id,
            ), $request),
            'Licencia aprobada y registrada en Sirha7.'
        ), 'No se pudo conectar al sistema biométrico. La licencia no cambió; inténtelo de nuevo.');
    }

    public function aprobarSinSirha7(int $id, AprobarCertificadoSinSirha7Request $request): JsonResponse
    {
        $this->authorize('aprobar', Licencia::findOrFail($id));

        $usuario = $request->user();

        return ApiResponse::ok(
            $this->respuesta($this->aprobacion->aprobarSinSirha7(
                $id,
                $request->string('nota')->trim()->toString(),
                $usuario->id,
                $usuario->servidor_id,
            ), $request),
            'Licencia aprobada sin registrarla en Sirha7.'
        );
    }

    public function anular(int $id, Request $request): JsonResponse
    {
        $this->authorize('anular', Licencia::findOrFail($id));

        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:5', 'max:500']],
            [],
            ['motivo' => 'motivo de la anulación'],
        );

        return $this->conBiometrico(fn () => ApiResponse::ok(
            $this->respuesta($this->licencias->anular($id, trim($datos['motivo']), $request->user()->id), $request),
            'Licencia anulada.'
        ), 'No se pudo conectar al sistema biométrico para retirar la licencia. No cambió nada; inténtelo de nuevo.');
    }

    /** Descarga el respaldo: vive en el disco privado. */
    public function documento(int $id): mixed
    {
        $licencia = Licencia::findOrFail($id);
        $this->authorize('ver', $licencia);

        $ruta = $this->licencias->documento($licencia);

        if (! $ruta) {
            return ApiResponse::error('La licencia no tiene el respaldo adjunto.', null, 404);
        }

        return Storage::disk('local')->download($ruta, $licencia->documento_nombre ?: "licencia-{$licencia->folio}.pdf");
    }

    private function respuesta(Licencia $licencia, Request $request): array
    {
        $licencia->load([
            'servidor.unidadAdministrativa', 'aprobadoPor.servidor', 'creadoPor.servidor', 'clausulaDeLicencia.contratoColectivo',
        ]);

        return (new LicenciaResource($licencia))->resolve($request);
    }
}

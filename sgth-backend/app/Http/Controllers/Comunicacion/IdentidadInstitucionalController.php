<?php

namespace App\Http\Controllers\Comunicacion;

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\VarianteIdentidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicacion\ActualizarDatosInstitucionalesRequest;
use App\Http\Requests\Comunicacion\ReutilizarRecursoIdentidadRequest;
use App\Http\Requests\Comunicacion\SubirRecursoIdentidadRequest;
use App\Http\Resources\Comunicacion\DatosInstitucionalesResource;
use App\Http\Resources\Comunicacion\RecursoIdentidadResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comunicacion\RecursoIdentidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La identidad institucional para quien la administra
 * (docs/diseno-comunicacion-social.md, 4; fase 2.1): cada variante del logo
 * con su versión vigente y las programadas, el historial, subir, volver a una
 * versión, anular una programada y los datos de la institución. Todo con
 * `gestionar-identidad-institucional`, que tiene el rol `responsable-identidad`.
 */
final class IdentidadInstitucionalController extends Controller
{
    private const RELACIONES = ['subidoPor.servidor', 'anuladaPor.servidor'];

    public function __construct(private readonly IdentidadInstitucionalServiceInterface $identidad) {}

    public function index(): JsonResponse
    {
        $variantes = $this->identidad->estado()->map(fn (array $e) => [
            'variante'    => $e['variante']->value,
            'etiqueta'    => $e['variante']->etiqueta(),
            'obligatoria' => $e['variante']->obligatoria(),
            'usos'        => $e['variante']->usos(),
            'requisito'   => $e['variante']->requisito(),
            'formatos'    => $e['variante']->formatos(),
            'respaldo'    => $e['variante']->respaldo()?->value,
            'vigente'     => $e['vigente']
                ? (new RecursoIdentidadResource($e['vigente']->loadMissing(self::RELACIONES)))->conVigente($e['vigente']->id)
                : null,
            // La variante que se ve hoy: ella misma, su respaldo o ninguna.
            'en_uso'      => $e['en_uso']?->value,
            'programadas' => $e['programadas']->map(fn (RecursoIdentidad $r) => new RecursoIdentidadResource($r))->values(),
        ])->values();

        return ApiResponse::ok([
            'variantes' => $variantes,
            'datos'     => new DatosInstitucionalesResource($this->identidad->datos()->load('actualizadoPor.servidor')),
        ], 'Identidad institucional.');
    }

    public function versiones(VarianteIdentidad $variante): JsonResponse
    {
        $vigenteId = $this->identidad->version($variante)?->id;

        return ApiResponse::ok(
            $this->identidad->versiones($variante)
                ->map(fn (RecursoIdentidad $r) => (new RecursoIdentidadResource($r))->conVigente($vigenteId))
                ->values(),
            "Versiones de {$variante->etiqueta()}.",
        );
    }

    public function store(SubirRecursoIdentidadRequest $request, VarianteIdentidad $variante): JsonResponse
    {
        $recurso = $this->identidad->subir(
            $variante,
            (string) $request->file('archivo')->getRealPath(),
            $request->validated('vigente_desde'),
            $request->validated('nota'),
            $request->user(),
        );

        return ApiResponse::created(
            (new RecursoIdentidadResource($recurso->load(self::RELACIONES)))->conVigente($this->identidad->version($variante)?->id),
            $recurso->vigente_desde->isFuture()
                ? 'Versión programada: rige desde el '.$recurso->vigente_desde->format('d/m/Y').'.'
                : 'Versión guardada: ya es la vigente.',
        );
    }

    public function reutilizar(ReutilizarRecursoIdentidadRequest $request, RecursoIdentidad $recurso): JsonResponse
    {
        $nueva = $this->identidad->reutilizar($recurso, $request->validated('vigente_desde'), $request->user());

        return ApiResponse::created(
            (new RecursoIdentidadResource($nueva->load(self::RELACIONES)))->conVigente($this->identidad->version($nueva->variante)?->id),
            'Se volvió a esa versión con una versión nueva; la historia queda como estaba.',
        );
    }

    public function anular(Request $request, RecursoIdentidad $recurso): JsonResponse
    {
        $recurso = $this->identidad->anular($recurso, $request->user());

        return ApiResponse::ok(
            new RecursoIdentidadResource($recurso->load(self::RELACIONES)),
            'Versión programada anulada: no llegará a regir.',
        );
    }

    /** El archivo de cualquier versión, incluida una programada, que aún no es pública. */
    public function archivo(RecursoIdentidad $recurso): BinaryFileResponse|JsonResponse
    {
        $ruta = $this->identidad->rutaDe($recurso);

        if (! is_file($ruta)) {
            return ApiResponse::noEncontrado('El archivo de esa versión no está en el servidor.');
        }

        return response()->file($ruta, ['Content-Type' => $recurso->mime, 'Cache-Control' => 'private, max-age=86400']);
    }

    public function actualizarDatos(ActualizarDatosInstitucionalesRequest $request): JsonResponse
    {
        $datos = $this->identidad->actualizarDatos($request->validated(), $request->user());

        return ApiResponse::ok(
            new DatosInstitucionalesResource($datos->load('actualizadoPor.servidor')),
            'Datos de la institución actualizados.',
        );
    }
}

<?php

namespace App\Http\Controllers\Autoservicio;

use App\Enums\EstadoEnvioResultado;
use App\Enums\EstadoOrdenExamen;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Autoservicio\EnviarResultadoRequest;
use App\Http\Resources\Autoservicio\MiEnvioResultadoResource;
use App\Http\Resources\Autoservicio\MiOrdenExamenResource;
use App\Http\Responses\ApiResponse;
use App\Services\Dispensario\EnvioResultadoService;
use App\Services\Dispensario\PdfOrdenExamenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «Mis exámenes» del Portal (fase 6 de docs/diseno-examenes-medicos.md, 5.7):
 * las órdenes del servidor, su impreso por si lo perdió, y enviar lo que le
 * dio el laboratorio para que un médico lo valide (D2, D9).
 */
final class MisExamenesController extends Controller
{
    private const POR_PAGINA_MAX = 50;

    public function __construct(
        private readonly EnvioResultadoService $envios,
        private readonly PdfOrdenExamenService $pdf,
    ) {}

    /** Las suyas, de la más reciente a la más antigua. Lo retirado no sale. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $pagina = $this->envios->ordenesDelServidor((int) ($request->user()->servidor_id ?? 0))
            ->with([
                'items:id,orden_examen_id,catalogo_examen_id,nota,estado,motivo_no_realizado',
                'items.examen:id,nombre',
                'solicitante:id,usuario_ti,servidor_id',
                'solicitante.servidor:id,nombre,apellido',
                'envios' => fn ($q) => $q->where('estado', '!=', EstadoEnvioResultado::RETIRADO),
            ])
            ->orderByDesc('fecha_solicitud')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 10), self::POR_PAGINA_MAX));

        return ApiResponse::paginadoDe(
            $pagina,
            // Sin `resolve()`: así Scramble infiere su forma y el Portal tiene los tipos.
            MiOrdenExamenResource::collection($pagina->getCollection()),
            'Mis órdenes de exámenes.',
        );
    }

    /** El impreso que se lleva al laboratorio. El de una anulada ya no sirve. */
    public function pdf(Request $request, int $orden): Response
    {
        $existente = $this->envios->ordenDeLaCuenta($request->user(), $orden);

        if ($existente->estado === EstadoOrdenExamen::ANULADA) {
            throw new ReglaNegocioException("La orden {$existente->folio} fue anulada: no la lleve al laboratorio.");
        }

        $resultado = $this->pdf->generarContent($existente->id);

        return response($resultado['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $resultado['filename'] . '"',
        ]);
    }

    public function enviar(EnviarResultadoRequest $request, int $orden): JsonResponse
    {
        $envio = $this->envios->enviar(
            $request->user(),
            $orden,
            $request->safe()->except('archivo'),
            $request->file('archivo'),
        );

        return ApiResponse::created(
            new MiEnvioResultadoResource($envio->refresh()),
            'Resultado enviado. Un médico del Dispensario lo revisará antes de agregarlo a su historia clínica.'
        );
    }

    public function retirar(Request $request, int $envio): JsonResponse
    {
        return ApiResponse::ok(
            new MiEnvioResultadoResource($this->envios->retirar($request->user(), $envio)),
            'Envío retirado.'
        );
    }

    /** El archivo que envió. */
    public function archivo(Request $request, int $envio): StreamedResponse
    {
        return $this->envios->descargar($this->envios->envioDeLaCuenta($request->user(), $envio));
    }
}

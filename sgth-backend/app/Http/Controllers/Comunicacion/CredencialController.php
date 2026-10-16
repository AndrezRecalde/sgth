<?php

namespace App\Http\Controllers\Comunicacion;

use App\Contracts\Comunicacion\CredencialServiceInterface;
use App\Enums\FormatoImpresionCredencial;
use App\Enums\MotivoRevocacionCredencial;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicacion\ImprimirCredencialesRequest;
use App\Http\Requests\Comunicacion\ListarCredencialesRequest;
use App\Http\Requests\Comunicacion\RegistrarDevolucionCredencialRequest;
use App\Http\Requests\Comunicacion\RevocarCredencialRequest;
use App\Http\Resources\Comunicacion\CredencialResource;
use App\Http\Resources\Comunicacion\ServidorCredencialResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comunicacion\Credencial;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Las credenciales de los servidores (docs/diseno-comunicacion-social.md, 6;
 * fase 3.1): el listado con el estado de cada una, el historial, imprimir,
 * revocar —que repone— y recibir la tarjeta cuando el servidor sale.
 *
 * Comunicación no las emite: nacen solas con la foto y el vínculo vigente.
 */
final class CredencialController extends Controller
{
    private const RELACIONES = ['impresiones.impresaPor.servidor', 'revocadaPor.servidor', 'devolucionRegistradaPor.servidor'];

    public function __construct(private readonly CredencialServiceInterface $credenciales) {}

    public function index(ListarCredencialesRequest $request): JsonResponse
    {
        $pagina = $this->credenciales->listar($request->validated());

        return ApiResponse::paginadoDe(
            $pagina,
            ServidorCredencialResource::collection($pagina->getCollection())->resolve($request),
            'Credenciales de los servidores.',
        );
    }

    /**
     * A dónde lleva el QR. La pantalla avisa si es una dirección local: un
     * teléfono no la abre, y en producción no se imprime así.
     */
    public function verificacion(): JsonResponse
    {
        $local = Credencial::verificacionEsLocal();

        return ApiResponse::ok([
            'url_base'            => rtrim((string) config('app.frontend_url'), '/').'/verificar/credencial/',
            'local'               => $local,
            'impresion_bloqueada' => $local && app()->isProduction(),
        ], 'Dirección de verificación de las credenciales.');
    }

    public function historial(Servidor $servidor): JsonResponse
    {
        return ApiResponse::ok(
            CredencialResource::collection($this->credenciales->historial($servidor)),
            'Historial de credenciales del servidor.',
        );
    }

    /**
     * El PDF para imprimir. Queda registrado lo que se imprimió de cada una:
     * así se sabe después qué tarjetas quedaron desactualizadas.
     */
    public function imprimir(ImprimirCredencialesRequest $request): Response
    {
        $formato = FormatoImpresionCredencial::from($request->validated('formato'));

        if ($request->filled('unidad_administrativa_id')) {
            $credenciales = $this->credenciales->deUnidad((int) $request->validated('unidad_administrativa_id'));

            if ($credenciales->isEmpty()) {
                throw new ReglaNegocioException('Esa unidad no tiene credenciales vigentes con foto para imprimir.');
            }

            if ($credenciales->count() > ImprimirCredencialesRequest::MAXIMO) {
                throw new ReglaNegocioException(
                    "Esa unidad tiene {$credenciales->count()} credenciales vigentes y se imprimen hasta "
                    .ImprimirCredencialesRequest::MAXIMO.' a la vez: elija una unidad que cuelgue de ella, o las credenciales.'
                );
            }
        } else {
            $ids = $request->validated('credenciales');
            $credenciales = Credencial::whereKey($ids)->get()
                ->sortBy(fn (Credencial $c) => array_search($c->id, array_map('intval', $ids), true))
                ->values();
        }

        $pdf = $this->credenciales->imprimir($credenciales, $formato, $request->user());
        $nombre = $credenciales->count() === 1
            ? 'credencial_'.$credenciales->first()->servidor->cedula.'.pdf'
            : 'credenciales_'.now()->format('Y-m-d_His').'.pdf';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    public function revocar(RevocarCredencialRequest $request, Credencial $credencial): JsonResponse
    {
        $resultado = $this->credenciales->revocar(
            $credencial,
            MotivoRevocacionCredencial::from($request->validated('motivo')),
            $request->validated('detalle'),
            $request->user(),
        );

        $repuesta = $resultado['repuesta'];

        return ApiResponse::ok(
            [
                'revocada' => new CredencialResource($resultado['revocada']->load(self::RELACIONES)),
                'repuesta' => $repuesta ? new CredencialResource($repuesta->load(self::RELACIONES)) : null,
            ],
            $repuesta
                ? 'Credencial revocada. Se repuso con otro código: imprima la nueva tarjeta.'
                : 'Credencial revocada. Se repondrá sola cuando el servidor tenga foto.',
        );
    }

    public function devolucion(RegistrarDevolucionCredencialRequest $request, Credencial $credencial): JsonResponse
    {
        $credencial = $this->credenciales->registrarDevolucion(
            $credencial,
            $request->validated('fecha'),
            $request->validated('nota'),
            $request->user(),
        );

        return ApiResponse::ok(
            new CredencialResource($credencial->load(self::RELACIONES)),
            'Devolución de la tarjeta registrada.',
        );
    }
}

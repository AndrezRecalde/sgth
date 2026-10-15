<?php

namespace App\Http\Controllers\Autoservicio;

use App\Contracts\Comunicacion\CredencialServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\Comunicacion\MiCredencialResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comunicacion\Credencial;
use App\Models\Expediente\Servidor;
use App\Services\Comunicacion\DatosCredencial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * «Mi credencial» del Portal (docs/diseno-comunicacion-social.md, 6.1; fase
 * 3.1): la credencial digital y su PDF. Siempre la del propio servidor.
 */
final class MiCredencialController extends Controller
{
    public function __construct(private readonly CredencialServiceInterface $credenciales) {}

    public function show(Request $request): JsonResponse
    {
        $servidor = $this->servidor($request);

        if (! $servidor) {
            return ApiResponse::ok(null, 'Su usuario no está vinculado a un expediente.');
        }

        $credencial = $this->credencial($servidor);

        // El porqué, sin repetir que no la tiene: eso lo dice la pantalla.
        if (! $credencial) {
            return ApiResponse::ok(null, match (true) {
                ! $servidor->estado                => 'No tiene un vínculo vigente con la institución.',
                ! $servidor->fotografiaVigente     => 'Se crea cuando Comunicación Social registre su foto.',
                default                            => 'Ya tiene foto: su credencial se crea esta noche.',
            });
        }

        return ApiResponse::ok(new MiCredencialResource($credencial), 'Su credencial.');
    }

    /** La credencial digital en PDF: no cuenta como una impresión de Comunicación. */
    public function pdf(Request $request): Response|JsonResponse
    {
        $servidor = $this->servidor($request);
        $credencial = $servidor ? $this->credencial($servidor) : null;

        if (! $credencial || ! $credencial->estaVigente()) {
            return ApiResponse::error('No tiene una credencial vigente para descargar.', null, 422);
        }

        return response($this->credenciales->pdfDigital($credencial), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="credencial_'.$servidor->cedula.'.pdf"',
        ]);
    }

    private function servidor(Request $request): ?Servidor
    {
        $servidorId = $request->user()->servidor_id;

        return $servidorId ? Servidor::with(DatosCredencial::RELACIONES_SERVIDOR)->find($servidorId) : null;
    }

    private function credencial(Servidor $servidor): ?Credencial
    {
        $credencial = $servidor->credencialViva;
        $credencial?->setRelation('servidor', $servidor);

        return $credencial;
    }
}

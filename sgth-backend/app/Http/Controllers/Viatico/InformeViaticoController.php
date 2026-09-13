<?php
namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\PdfInformeViaticoService;
use Illuminate\Http\Response;

class InformeViaticoController extends Controller
{
    public function __construct(
        private PdfInformeViaticoService $service
    ) {}

    /**
     * Descarga el PDF de solicitud de viático
     */
    public function generarSolicitud(
        string $identificador
    ): Response {
        $result = $this->service->generarSolicitudContent(
            $this->autorizar($identificador)
        );

        return response($result['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' .
                $result['filename'] . '"',
        ]);
    }

    /**
     * Descarga el PDF de informe de liquidación
     */
    public function generarEnlace(
        string $identificador
    ): Response {
        $result = $this->service->generarInformeContent(
            $this->autorizar($identificador)
        );

        return response($result['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' .
                $result['filename'] . '"',
        ]);
    }

    public function generarComprobanteContabilidad(
        string $identificador
    ): Response {
        $result = $this->service->generarComprobanteContabilidad(
            $this->autorizar($identificador)
        );

        return response($result['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' .
                $result['filename'] . '"',
        ]);
    }

    /**
     * El id del viático, si el usuario puede verlo.
     *
     * Los PDF no comprobaban nada y el código es predecible (unidad, año y
     * secuencial): cualquiera descargaba la solicitud de otro, con su cuenta
     * bancaria. Se imprime bajo la misma regla con la que se lee.
     */
    private function autorizar(string $identificador): int
    {
        $viatico = is_numeric($identificador)
            ? Viatico::findOrFail((int) $identificador)
            : Viatico::where('codigo_viatico', $identificador)->firstOrFail();

        $this->authorize('ver', $viatico);

        return $viatico->id;
    }
}

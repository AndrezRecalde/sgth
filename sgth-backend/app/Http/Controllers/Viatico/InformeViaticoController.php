<?php
namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\PdfInformeViaticoService;
use Illuminate\Http\Request;
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
     * La orden de pago del anticipo.
     *
     * A diferencia de los otros tres, este documento no lo abre cualquiera que
     * vea el viático: lo firma quien lo genera, así que solo lo emite quien
     * opera los viáticos. Si lo abriera el propio viajero, quedaría sellado
     * como el funcionario que preparó su pago.
     */
    public function generarOrdenAnticipo(
        string $identificador,
        Request $request
    ): Response {
        $viatico = $this->buscar($identificador);
        $this->authorize('operar', $viatico);

        $result = $this->service->generarOrdenAnticipo(
            $viatico->id,
            $request->user()?->servidor
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
        $viatico = $this->buscar($identificador);

        $this->authorize('ver', $viatico);

        return $viatico->id;
    }

    /** El viático por id o por código, como lo trae el enlace. */
    private function buscar(string $identificador): Viatico
    {
        return is_numeric($identificador)
            ? Viatico::findOrFail((int) $identificador)
            : Viatico::where('codigo_viatico', $identificador)->firstOrFail();
    }
}

<?php
namespace App\Services\Viatico;

use App\Enums\EstadoViatico;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\Servidor;
use App\Models\Viatico\Viatico;
use Spatie\LaravelPdf\Facades\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class PdfInformeViaticoService
{
    /**
     * Genera el PDF de SOLICITUD de viático
     * (antes de la comisión)
     */
    public function generarSolicitud(int|string $identificador): string
    {
        $viatico = $this->cargarViatico($identificador);

        $prefecto = $this->obtenerPrefecto();

        $nombreArchivo = "solicitud_{$viatico->codigo_viatico}.pdf";
        $path = "informes-viatico/{$nombreArchivo}";

        $pdf = Pdf::view('pdf.viaticos.solicitud-viatico', [
            'viatico'  => $viatico,
            'prefecto' => $prefecto,
            'logo'     => public_path('images/logo-gadpe.png'),
        ])->format('A4');

        Storage::put($path, $pdf->content());

        return URL::temporarySignedRoute(
            'viaticos.informe.descargar',
            now()->addMinutes(30),
            ['archivo' => $nombreArchivo]
        );
    }

    /**
     * Genera el PDF de INFORME DE LIQUIDACIÓN
     * (después de la comisión)
     */
    public function generarInformeLiquidacion(
        int|string $identificador
    ): string {
        $viatico = $this->cargarViatico($identificador);

        $estadosPermitidos = [
            EstadoViatico::PENDIENTE_LIQUIDACION->value,
            EstadoViatico::LIQUIDADO->value,
            EstadoViatico::CONTABILIZADO->value,
        ];

        if (!in_array($viatico->estado->value, $estadosPermitidos)) {
            throw new ReglaNegocioException(
                'El viático debe estar en estado ' .
                'pendiente de liquidación, liquidado ' .
                'o contabilizado para generar el informe.'
            );
        }

        $prefecto = $this->obtenerPrefecto();

        $nombreArchivo = "informe_{$viatico->codigo_viatico}.pdf";
        $path = "informes-viatico/{$nombreArchivo}";

        $pdf = Pdf::view('pdf.viaticos.informe-liquidacion', [
            'viatico'  => $viatico,
            'prefecto' => $prefecto,
            'logo'     => public_path('images/logo-gadpe.png'),
        ])->format('A4');

        Storage::put($path, $pdf->content());

        return URL::temporarySignedRoute(
            'viaticos.informe.descargar',
            now()->addMinutes(30),
            ['archivo' => $nombreArchivo]
        );
    }

    /**
     * Mantiene compatibilidad con el controller existente
     */
    public function generarEnlaceTemporal(
        int|string $identificador
    ): string {
        return $this->generarInformeLiquidacion($identificador);
    }

    private function cargarViatico(int|string $identificador): Viatico
    {
        $query = Viatico::with([
            'servidor.puesto.cargo',
            'servidor.puesto.unidadAdministrativa',
            'servidor.cuentasBancarias' => fn($q) =>
                $q->where('es_principal_viatico', true)
                  ->with('entidadFinanciera')
                  ->limit(1),
            'tramos.empresa.catalogo',
            'tramos.origenProvincia',
            'tramos.origenCanton',
            'tramos.destinoProvincia',
            'tramos.destinoCanton',
            'tramos.autorizacionVuelo',
            'liquidacion.actividades',
            'liquidacion.detallesFactura.categoria',
            'todosServidores.servidor.puesto.cargo',
        ]);

        return is_numeric($identificador)
            ? $query->findOrFail((int) $identificador)
            : $query->where('codigo_viatico', $identificador)
                    ->firstOrFail();
    }

    private function obtenerPrefecto(): ?Servidor
    {
        return Servidor::whereHas('puesto', function ($q) {
            $q->whereHas('cargo', function ($q2) {
                $q2->where('nombre', 'like', '%Prefect%');
            });
        })->with('puesto.cargo')->first();
    }
}

<?php
namespace App\Services\Viatico;

use App\Enums\EstadoViatico;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\Servidor;
use App\Models\Viatico\Viatico;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfInformeViaticoService
{
    /**
     * Genera el PDF de solicitud y retorna
     * el contenido como string para ser
     * descargado directamente
     */
    public function generarSolicitudContent(
        int|string $identificador
    ): array {
        $viatico  = $this->cargarViatico($identificador);
        $prefecto = $this->obtenerPrefecto();

        $pdf = Pdf::loadView(
            'pdf.viaticos.solicitud-viatico',
            [
                'viatico'  => $viatico,
                'prefecto' => $prefecto,
                'logo'     => public_path('images/logo-gadpe.png'),
            ]
        )->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => "solicitud_{$viatico->codigo_viatico}.pdf",
        ];
    }

    /**
     * Genera el PDF de informe de liquidación
     */
    public function generarInformeContent(
        int|string $identificador
    ): array {
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

        $pdf = Pdf::loadView(
            'pdf.viaticos.informe-comision',
            [
                'viatico'  => $viatico,
                'prefecto' => $prefecto,
                'logo'     => public_path('images/logo-gadpe.png'),
            ]
        )->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => "informe_{$viatico->codigo_viatico}.pdf",
        ];
    }

    /**
     * Mantiene compatibilidad — ahora lanza excepción
     * indicando que se debe usar el nuevo método
     */
    public function generarEnlaceTemporal(
        int|string $identificador
    ): string {
        // Devuelve URL directa al endpoint
        return route('viaticos.informe.generar-enlace', [
            'identificador' => $identificador,
        ]);
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

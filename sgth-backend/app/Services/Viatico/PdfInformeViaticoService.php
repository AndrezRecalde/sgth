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

        $zonaValue = $viatico->zona instanceof \BackedEnum
            ? $viatico->zona->value
            : (string) $viatico->zona;

        $modalidadValue = $viatico->modalidad_anticipo instanceof \BackedEnum
            ? $viatico->modalidad_anticipo->value
            : (string) $viatico->modalidad_anticipo;

        $zonaLabel = match($zonaValue) {
            'dentro_provincia' => 'Dentro de la Provincia',
            'fuera_provincia'  => 'Fuera de la Provincia',
            'exterior'         => 'Exterior (Internacional)',
            default            => ucfirst(
                str_replace('_', ' ', $zonaValue)
            ),
        };

        $modalidadLabel = match($modalidadValue) {
            'total'        => 'Anticipo Total (100%)',
            'parcial'      => 'Anticipo Parcial',
            'sin_anticipo' => 'Sin Anticipo',
            default        => ucfirst($modalidadValue),
        };

        $pdf = Pdf::loadView(
            'pdf.viaticos.solicitud-viatico',
            [
                'viatico'        => $viatico,
                'prefecto'       => $prefecto,
                'jefeUnidad'     => $this->obtenerJefeUnidad($viatico),
                'logo'           => public_path('images/logo-gadpe.png'),
                'zonaLabel'      => $zonaLabel,
                'modalidadLabel' => $modalidadLabel,
                'modalidadValue' => $modalidadValue,
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

        $zonaValue = $viatico->zona instanceof \BackedEnum
            ? $viatico->zona->value
            : (string) $viatico->zona;

        $modalidadValue = $viatico->modalidad_anticipo instanceof \BackedEnum
            ? $viatico->modalidad_anticipo->value
            : (string) $viatico->modalidad_anticipo;

        $zonaLabel = match($zonaValue) {
            'dentro_provincia' => 'Dentro de la Provincia',
            'fuera_provincia'  => 'Fuera de la Provincia',
            'exterior'         => 'Exterior (Internacional)',
            default            => ucfirst(
                str_replace('_', ' ', $zonaValue)
            ),
        };

        $modalidadLabel = match($modalidadValue) {
            'total'        => 'Anticipo Total (100%)',
            'parcial'      => 'Anticipo Parcial',
            'sin_anticipo' => 'Sin Anticipo',
            default        => ucfirst($modalidadValue),
        };

        $pdf = Pdf::loadView(
            'pdf.viaticos.informe-comision',
            [
                'viatico'        => $viatico,
                'prefecto'       => $prefecto,
                'jefeUnidad'     => $this->obtenerJefeUnidad($viatico),
                'logo'           => public_path('images/logo-gadpe.png'),
                'zonaLabel'      => $zonaLabel,
                'modalidadLabel' => $modalidadLabel,
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

    private function obtenerJefeUnidad(
        Viatico $viatico
    ): ?Servidor {
        $unidadId = $viatico->servidor
            ?->puesto
            ?->unidad_administrativa_id;

        if (!$unidadId) return null;

        return Servidor::whereHas('puesto', function ($q)
            use ($unidadId) {
                $q->where('es_jefe', true)
                  ->where('unidad_administrativa_id', $unidadId);
            }
        )->with(['puesto.cargo'])->first();
    }

    public function generarComprobanteContabilidad(
        int|string $identificador
    ): array {
        $viatico = $this->cargarViatico($identificador);

        if ($viatico->estado->value !== 'contabilizado') {
            throw new \App\Exceptions\ReglaNegocioException(
                'El viático debe estar contabilizado para '
                . 'generar el comprobante financiero.'
            );
        }

        $prefecto      = $this->obtenerPrefecto();
        $jefeUnidad    = $this->obtenerJefeUnidad($viatico);
        $directorFinanciero = $this->obtenerDirectorFinanciero();

        // Número en letras
        $total       = (float) ($viatico->monto_anticipo ?? 0);
        $totalLetras = \App\Helpers\NumeroALetras::convertir($total);

        // Agrupar facturas por categoría
        $facturasPorCategoria = $this->agruparFacturasPorCategoria(
            $viatico
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'pdf.viaticos.comprobante-contabilidad',
            [
                'viatico'             => $viatico,
                'prefecto'            => $prefecto,
                'jefeUnidad'          => $jefeUnidad,
                'directorFinanciero'  => $directorFinanciero,
                'logo'                => public_path('images/logo-gadpe.png'),
                'totalLetras'         => $totalLetras,
                'facturasPorCategoria'=> $facturasPorCategoria,
                'modalidadLabel'      => match(
                    $viatico->modalidad_anticipo instanceof \BackedEnum
                        ? $viatico->modalidad_anticipo->value
                        : (string) $viatico->modalidad_anticipo
                ) {
                    'total'        => 'DEFINITIVA',
                    'parcial'      => 'PARCIAL',
                    'sin_anticipo' => 'SIN ANTICIPO',
                    default        => 'DEFINITIVA',
                },
            ]
        )->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => "comprobante_{$viatico->codigo_viatico}.pdf",
        ];
    }

    private function obtenerDirectorFinanciero(): ?\App\Models\Expediente\Servidor
    {
        return \App\Models\Expediente\Servidor::whereHas('puesto', function ($q) {
            $q->where('es_jefe', true)
              ->whereHas('unidadAdministrativa', function ($q2) {
                  $q2->where('nombre', 'like', '%Financier%');
              });
        })->with(['puesto.cargo'])->first();
    }

    private function agruparFacturasPorCategoria(
        \App\Models\Viatico\Viatico $viatico
    ): array {
        $categorias = [
            1  => ['nombre' => 'Hospedaje',                   'total' => 0],
            2  => ['nombre' => 'Alimentación',                'total' => 0],
            3  => ['nombre' => 'Transporte terrestre',        'total' => 0],
            4  => ['nombre' => 'Pasaje aéreo',                'total' => 0],
            5  => ['nombre' => 'Combustible',                 'total' => 0],
            6  => ['nombre' => 'Peaje',                       'total' => 0],
            10 => ['nombre' => 'Inscripción / Registro',      'total' => 0],
            13 => ['nombre' => 'Otro',                        'total' => 0],
        ];

        foreach (
            $viatico->liquidacion?->detallesFactura ?? []
            as $factura
        ) {
            $catId = $factura->categoria_factura_id;
            if (isset($categorias[$catId])) {
                $categorias[$catId]['total'] += (float) $factura->monto;
            } else {
                $categorias[13]['total'] += (float) $factura->monto;
            }
        }

        return $categorias;
    }
}

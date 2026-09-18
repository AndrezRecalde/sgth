<?php
namespace App\Services\Viatico;

use App\Enums\EstadoViatico;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\Servidor;
use App\Models\Viatico\Viatico;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfInformeViaticoService
{
    public function __construct(
        private readonly CalculoViaticoService $calculo,
        private readonly FirmanteViaticoService $firmantes,
    ) {}

    /**
     * Genera el PDF de solicitud y retorna
     * el contenido como string para ser
     * descargado directamente
     */
    public function generarSolicitudContent(
        int|string $identificador
    ): array {
        $viatico = $this->cargarViatico($identificador);

        [$zonaLabel, $modalidadLabel, $modalidadValue] = $this->etiquetas($viatico);

        $pdf = Pdf::loadView(
            'pdf.viaticos.solicitud-viatico',
            [
                'viatico'        => $viatico,
                'firmas'         => $this->firmantes->paraDocumento($viatico, FirmanteViaticoService::SOLICITUD),
                'logo'           => public_path('images/logo-gadpe.png'),
                'zonaLabel'      => $zonaLabel,
                'modalidadLabel' => $modalidadLabel,
                'modalidadValue' => $modalidadValue,
                'calculo'        => $this->calculo->resumen($viatico),
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

        [$zonaLabel, $modalidadLabel] = $this->etiquetas($viatico);

        $pdf = Pdf::loadView(
            'pdf.viaticos.informe-comision',
            [
                'viatico'        => $viatico,
                'firmas'         => $this->firmantes->paraDocumento($viatico, FirmanteViaticoService::INFORME),
                'logo'           => public_path('images/logo-gadpe.png'),
                'zonaLabel'      => $zonaLabel,
                'modalidadLabel' => $modalidadLabel,
                'calculo'        => $this->calculo->resumen($viatico),
            ]
        )->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => "informe_{$viatico->codigo_viatico}.pdf",
        ];
    }

    /**
     * Zona y modalidad del anticipo tal como se imprimen.
     *
     * El anticipo es el 70 % del monto: la solicitud imprimía «Anticipo Total
     * (100%) (70%)» porque la etiqueta decía una cosa y la plantilla le añadía
     * otra. Estaba repetido en dos métodos.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function etiquetas(Viatico $viatico): array
    {
        $zona = $viatico->zona instanceof \BackedEnum
            ? $viatico->zona->value
            : (string) $viatico->zona;

        $modalidad = $viatico->modalidad_anticipo instanceof \BackedEnum
            ? $viatico->modalidad_anticipo->value
            : (string) $viatico->modalidad_anticipo;

        $zonaLabel = match ($zona) {
            'dentro_provincia' => 'Dentro de la Provincia',
            'fuera_provincia'  => 'Fuera de la Provincia',
            'exterior'         => 'Exterior (Internacional)',
            default            => ucfirst(str_replace('_', ' ', $zona)),
        };

        $modalidadLabel = match ($modalidad) {
            'total'        => 'Anticipo',
            'sin_anticipo' => 'Sin Anticipo',
            default        => ucfirst(str_replace('_', ' ', $modalidad)),
        };

        return [$zonaLabel, $modalidadLabel, $modalidad];
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
        ]);

        return is_numeric($identificador)
            ? $query->findOrFail((int) $identificador)
            : $query->where('codigo_viatico', $identificador)
                    ->firstOrFail();
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

        // El comprobante imprime lo liquidado, no el anticipo: un viático sin
        // anticipo salía en $0,00. Decidido con Gestión Financiera.
        $calculo     = $this->calculo->resumen($viatico);
        $totalLetras = \App\Helpers\NumeroALetras::convertir($calculo['reconocido']);

        // Agrupar facturas por categoría
        $facturasPorCategoria = $this->agruparFacturasPorCategoria(
            $viatico
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'pdf.viaticos.comprobante-contabilidad',
            [
                'viatico'             => $viatico,
                'firmas'              => $this->firmantes->paraDocumento($viatico, FirmanteViaticoService::COMPROBANTE),
                'logo'                => public_path('images/logo-gadpe.png'),
                'totalLetras'         => $totalLetras,
                'calculo'             => $calculo,
                'facturasPorCategoria'=> $facturasPorCategoria,
                'modalidadLabel'      => match(
                    $viatico->modalidad_anticipo instanceof \BackedEnum
                        ? $viatico->modalidad_anticipo->value
                        : (string) $viatico->modalidad_anticipo
                ) {
                    'total'        => 'DEFINITIVA',
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

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
        private readonly \App\Services\Estructura\FirmanteOrganigramaService $organigrama,
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
                ...$this->comunes($viatico),
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
                ...$this->comunes($viatico),
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
            'tramos.catalogo', 'tramos.empresa.catalogo',
            'tramos.origenProvincia',
            'tramos.origenCanton',
            'tramos.destinoProvincia',
            'tramos.destinoCanton',
            'liquidacion.actividades',
            'liquidacion.contabilizadoPor.servidor.puesto.cargo',
            'liquidacion.detallesFactura.categoria',
            'partidaPresupuestaria',
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
                ...$this->comunes($viatico),
                'totalLetras'         => $totalLetras,
                'calculo'             => $calculo,
                'facturasPorCategoria'=> $facturasPorCategoria,
                'elaborado'           => $this->elaboradoPor($viatico),
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

    /**
     * Quién elaboró el comprobante: el de Financiero que lo contabilizó.
     *
     * La plantilla pedía `$liquidacion->createdBy->name`, y `users` no tiene
     * columna `name`, así que la firma salía sin nombre; además `created_by`
     * es quien creó la liquidación, que es el servidor que viajó. El
     * comprobante lo produce Financiero al contabilizar, y eso sí está
     * guardado en `contabilizado_por`.
     *
     * @return array{nombre: string, cargo: ?string}|null
     */
    private function elaboradoPor(Viatico $viatico): ?array
    {
        $servidor = $viatico->liquidacion?->contabilizadoPor?->servidor;

        if (! $servidor) {
            return null;
        }

        return [
            'nombre' => $this->organigrama->nombreCompleto($servidor),
            'cargo'  => $servidor->puesto?->cargo?->nombre,
        ];
    }

    /**
     * Lo justificado, por grupo y categoría, como se imprime.
     *
     * Antes la lista de categorías estaba escrita a mano con ocho ids fijos, y
     * la plantilla solo imprimía seis de ellos: lo que caía en «Inscripción /
     * Registro» o en «Otro» —y todo lo que no estuviera en la lista, que el
     * catálogo tiene trece— se sumaba al TOTAL MOVILIZACIÓN sin fila propia.
     * El bloque no cuadraba: cuatro ceros y un total de cien dólares.
     *
     * Ahora cada comprobante va al grupo de su categoría (`viatico` para
     * hospedaje y alimentación, `movilizacion` para el resto) y cada categoría
     * usada se imprime. Lo que suma, se ve.
     *
     * @return array<string, array{filas: list<array{nombre: string, total: float}>, total: float}>
     */
    private function agruparFacturasPorCategoria(
        \App\Models\Viatico\Viatico $viatico
    ): array {
        $grupos = [
            'viatico'      => ['filas' => [], 'total' => 0.0],
            'movilizacion' => ['filas' => [], 'total' => 0.0],
        ];

        $porCategoria = [];

        foreach ($viatico->liquidacion?->detallesFactura ?? [] as $factura) {
            $categoria = $factura->categoria;

            // Sin categoría —o con una borrada— va a movilización y con nombre
            // propio: un monto sin fila es lo que se está arreglando.
            $grupo = $categoria?->grupo === 'viatico' ? 'viatico' : 'movilizacion';
            $clave = $grupo.'|'.($categoria?->id ?? 0);

            $porCategoria[$clave] ??= [
                'grupo'  => $grupo,
                'orden'  => $categoria?->orden ?? PHP_INT_MAX,
                'nombre' => $categoria?->nombre ?: 'Sin categoría',
                'total'  => 0.0,
            ];
            $porCategoria[$clave]['total'] += (float) $factura->monto;
        }

        uasort($porCategoria, fn ($a, $b) => [$a['orden'], $a['nombre']] <=> [$b['orden'], $b['nombre']]);

        foreach ($porCategoria as $fila) {
            $grupos[$fila['grupo']]['filas'][] = [
                'nombre' => $fila['nombre'],
                'total'  => $fila['total'],
            ];
            $grupos[$fila['grupo']]['total'] += $fila['total'];
        }

        return $grupos;
    }

    /**
     * Lo que los tres documentos dicen igual: el membrete, el nombre de quien
     * viaja y a dónde fue.
     *
     * Cada plantilla armaba el nombre a su manera —la firma de la solicitud
     * salía «RECALDE CRISTHIAN» mientras la cabecera del mismo papel decía
     * «Recalde Solano Cristhian Andrés»— y cada una calculaba el destino por
     * su cuenta. Se arma una vez y se reparte.
     */
    private function comunes(Viatico $viatico): array
    {
        $servidor = $viatico->servidor;

        return [
            ...$this->membrete($viatico),
            'servidorNombre'  => $servidor ? $this->organigrama->nombreCompleto($servidor) : '—',
            'destino'         => \App\Support\DestinoDelViatico::texto($viatico),
            'destinoCiudades' => \App\Support\DestinoDelViatico::textoDeCiudades($viatico),
        ];
    }

    /**
     * El logo y los datos vigentes el día de la solicitud (D3): los tres
     * papeles del trámite —solicitud, informe y comprobante— salen iguales.
     */
    private function membrete(\App\Models\Viatico\Viatico $viatico): array
    {
        return app(\App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface::class)->paraDocumento(
            $viatico->fecha_solicitud ? \Illuminate\Support\Carbon::parse($viatico->fecha_solicitud) : null,
        );
    }
}

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
                \App\Support\CuentaDeViatico::condicion($q)
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

        // Y, debajo, lo que se gira. Hasta ahora la única cifra en letras era
        // la liquidada —$160 escritos mientras se transfieren $48—, y en un
        // documento de pago la cifra en letras es la que prevalece.
        // Respondido por Gestión Financiera el 2026-11-18 (D3).
        $saldoLetras = \App\Helpers\NumeroALetras::convertir(abs($calculo['saldo']));

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
                'saldoLetras'         => $saldoLetras,
                'calculo'             => $calculo,
                'facturasPorCategoria'=> $facturasPorCategoria,
                'elaborado'           => $this->elaboradoPor($viatico),
                // El «TIPO» es la etapa del pago, no la modalidad del anticipo:
                // definida por Gestión Financiera el 2026-11-23 (S2). Este
                // documento es siempre el definitivo, también cuando el viático
                // se tramitó sin anticipo; la orden del anticipo es la previa.
                'modalidadLabel'      => 'DEFINITIVA',
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
    /**
     * La orden de pago del anticipo.
     *
     * Se imprime desde que el viático está aprobado, antes de que el dinero
     * salga: es el papel con el que Financiero pide el egreso (S3). Por eso no
     * es un segundo comprobante contable y lleva el tipo PREVIA.
     *
     * `$elabora` es quien la genera —el funcionario de Financiero que prepara
     * el pago—, y se sella la primera vez: reimprimirla no le cambia el nombre
     * a un papel ya firmado (S4).
     */
    public function generarOrdenAnticipo(
        int|string $identificador,
        ?Servidor $elabora = null
    ): array {
        $viatico = $this->cargarViatico($identificador);

        if ($this->valorDe($viatico->modalidad_anticipo) === 'sin_anticipo') {
            throw new ReglaNegocioException(
                'El viático se solicitó sin anticipo: no hay pago que ordenar.'
            );
        }

        $sinOrden = [
            EstadoViatico::SOLICITADO->value,
            EstadoViatico::CANCELADO->value,
            EstadoViatico::RECHAZADO->value,
        ];

        if (in_array($viatico->estado->value, $sinOrden, true)) {
            throw new ReglaNegocioException(
                'La orden de pago del anticipo se emite desde que el viático está aprobado.'
            );
        }

        $this->firmantes->sellarOrdenDeAnticipo($viatico, $elabora);

        // Antes de entregarlo no hay monto guardado —`monto_anticipo` queda en
        // cero hasta ese momento—, así que se anuncia el previsto, que es con
        // lo que se ordena el pago.
        $anticipo = (float) ($viatico->monto_anticipo ?? 0) > 0
            ? (float) $viatico->monto_anticipo
            : round((float) $viatico->monto_calculado * CalculoViaticoService::PORCENTAJE_JUSTIFICABLE, 2);

        $pdf = Pdf::loadView(
            'pdf.viaticos.orden-pago-anticipo',
            [
                'viatico'        => $viatico,
                'firmas'         => $this->firmantes->paraDocumento($viatico, FirmanteViaticoService::ORDEN),
                ...$this->comunes($viatico),
                'anticipo'       => $anticipo,
                'anticipoLetras' => \App\Helpers\NumeroALetras::convertir($anticipo),
                'porcentaje'     => CalculoViaticoService::PORCENTAJE_JUSTIFICABLE * 100,
                'cuenta'         => \App\Support\CuentaDeViatico::de($viatico->servidor),
                'fechaOrden'     => $viatico->fecha_anticipo
                    ? \Illuminate\Support\Carbon::parse($viatico->fecha_anticipo)
                    : now(),
            ]
        )->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => "orden_anticipo_{$viatico->codigo_viatico}.pdf",
        ];
    }

    /** El valor de un enum que el modelo puede devolver ya casteado. */
    private function valorDe(mixed $valor): string
    {
        return $valor instanceof \BackedEnum ? (string) $valor->value : (string) $valor;
    }

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
            'anticipoDetalle' => $this->detalleDelAnticipo($viatico),
        ];
    }

    /**
     * Cuándo salió el anticipo y con qué documento, para la línea que lo
     * descuenta.
     *
     * El comprobante y el informe lo restaban a secas —«Anticipo entregado:
     * $ 112.00»—, sin fecha ni respaldo, y había que buscarlo en otra parte.
     * Pedido por Gestión Financiera (2026-11-23, A2). El número es el que
     * escriben al entregarlo: confirmaron (S1) que ahí anotan el comprobante
     * de pago que emite su propio sistema.
     *
     * Devuelve cadena vacía mientras no haya nada que decir, para que un
     * viático de antes de este cambio salga como salía.
     */
    private function detalleDelAnticipo(Viatico $viatico): string
    {
        if ((float) ($viatico->monto_anticipo ?? 0) <= 0) {
            return '';
        }

        $partes = [];

        if ($viatico->fecha_anticipo) {
            $partes[] = 'el '.\Illuminate\Support\Carbon::parse($viatico->fecha_anticipo)->format('d/m/Y');
        }

        if ($viatico->numero_resolucion) {
            $partes[] = 'comprobante N.° '.$viatico->numero_resolucion;
        }

        return $partes ? ' '.implode(', ', $partes) : '';
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

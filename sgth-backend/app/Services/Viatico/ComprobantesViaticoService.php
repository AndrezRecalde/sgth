<?php

namespace App\Services\Viatico;

use App\Enums\EstadoViatico;
use App\Exceptions\ReglaNegocioException;
use App\Models\User;
use App\Models\Viatico\FacturaViatico;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use App\Support\RucEcuador;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La revisión de Financiero sobre cada comprobante de la liquidación.
 *
 * Financiero solo podía contabilizar la liquidación entera o devolverla, sin
 * dejar constancia de qué comprobante estaba mal, y sin ninguna ayuda para
 * detectar un RUC inventado, una factura de otra fecha o la misma factura
 * presentada en dos viáticos.
 *
 * Decidido con el usuario:
 * - Cada comprobante se acepta u observa con motivo. Solo se contabiliza con
 *   todos aceptados; con alguno observado, se devuelve a corrección.
 * - Al volver a guardar los comprobantes, los que no cambiaron conservan su
 *   revisión; los nuevos o modificados vuelven a pendientes.
 * - Los controles automáticos (RUC, fecha, duplicados) avisan a Financiero,
 *   no impiden guardar: un ticket de peaje o un proveedor extranjero no tienen
 *   por qué cumplirlos.
 */
final class ComprobantesViaticoService
{
    public const PENDIENTE = 'pendiente';
    public const ACEPTADA  = 'aceptada';
    public const OBSERVADA = 'observada';

    /** Días calendario tras el regreso en que un comprobante sigue siendo válido. */
    public const DIAS_TRAS_EL_REGRESO = 5;

    /** Lo que identifica a un comprobante al compararlo con su versión anterior. */
    private const CAMPOS_IDENTIDAD = [
        'categoria_factura_id', 'tipo_comprobante', 'numero_factura', 'numero_ticket',
        'ruc_proveedor', 'fecha_factura', 'monto',
    ];

    public function __construct(
        private readonly ViaticoEstadoService $estados,
    ) {}

    /**
     * Aceptar u observar un comprobante. Con la liquidación presentada
     * (viático liquidado) y nunca en un viático en el que viaja quien revisa:
     * revisar es parte de contabilizar.
     */
    public function revisar(Viatico $viatico, int $facturaId, User $user, string $decision, ?string $observacion): FacturaViatico
    {
        return DB::transaction(function () use ($viatico, $facturaId, $user, $decision, $observacion) {
            $viatico = Viatico::lockForUpdate()->findOrFail($viatico->id);

            if ($viatico->estado !== EstadoViatico::LIQUIDADO) {
                throw new ReglaNegocioException('Los comprobantes se revisan con la liquidación presentada.');
            }

            if ($this->estados->viajaEn($viatico, $user)) {
                throw new ReglaNegocioException(
                    'No puede revisar los comprobantes de un viático en el que viaja: debe hacerlo otra persona de Financiero.'
                );
            }

            $factura = FacturaViatico::whereHas('liquidacion', fn ($q) => $q->where('viatico_id', $viatico->id))
                ->findOrFail($facturaId);

            $factura->update([
                'estado_revision'      => $decision,
                'observacion_revision' => $decision === self::OBSERVADA ? $observacion : null,
                'revisado_por'         => $user->id,
                'revisado_en'          => now(),
            ]);

            return $factura;
        });
    }

    /**
     * Para contabilizar, todos los comprobantes aceptados. Lo llama
     * `ViaticoEstadoService::contabilizar` con la fila ya bloqueada.
     */
    public function asegurarTodoAceptado(LiquidacionViatico $liquidacion): void
    {
        $conteo = $liquidacion->detallesFactura()
            ->selectRaw('estado_revision, count(*) as total')
            ->groupBy('estado_revision')
            ->pluck('total', 'estado_revision');

        if ($conteo->sum() === 0) {
            throw new ReglaNegocioException('La liquidación no tiene comprobantes que contabilizar.');
        }

        $observados = (int) ($conteo[self::OBSERVADA] ?? 0);
        $pendientes = (int) ($conteo[self::PENDIENTE] ?? 0);

        if ($observados > 0) {
            throw new ReglaNegocioException(
                "Hay {$observados} comprobante(s) observado(s): devuelva la liquidación a corrección."
            );
        }

        if ($pendientes > 0) {
            throw new ReglaNegocioException(
                "Faltan {$pendientes} comprobante(s) por revisar antes de contabilizar."
            );
        }
    }

    /**
     * Reemplaza los comprobantes de la liquidación conservando la revisión de
     * los que no cambiaron. Va dentro de la transacción de quien guarda.
     *
     * @param list<array<string, mixed>> $nuevos
     */
    public function reemplazar(LiquidacionViatico $liquidacion, array $nuevos): void
    {
        $revisiones = $liquidacion->detallesFactura()->get()
            ->groupBy(fn (FacturaViatico $f) => $this->identidad($f->only(self::CAMPOS_IDENTIDAD)));

        $liquidacion->detallesFactura()->delete();

        foreach ($nuevos as $datos) {
            $anterior = $revisiones->get($this->identidad($datos))?->shift();

            FacturaViatico::create(array_merge($datos, [
                'liquidacion_viatico_id' => $liquidacion->id,
            ], $anterior ? [
                'estado_revision'      => $anterior->estado_revision,
                'observacion_revision' => $anterior->observacion_revision,
                'revisado_por'         => $anterior->revisado_por,
                'revisado_en'          => $anterior->revisado_en,
            ] : []));
        }
    }

    /**
     * Anota en cada comprobante sus alertas: RUC inválido, fecha fuera de la
     * comisión o factura ya presentada en otro viático.
     *
     * @param Collection<int, FacturaViatico> $facturas
     * @return Collection<int, FacturaViatico>
     */
    public function conAlertas(Collection $facturas, Viatico $viatico): Collection
    {
        $duplicadas = $this->duplicadas($facturas);

        return $facturas->each(fn (FacturaViatico $f) => $f->setAttribute(
            'alertas',
            $this->alertas($f, $viatico, $duplicadas)
        ));
    }

    /**
     * @param array<string, string> $duplicadas clave RUC|número → códigos de los otros viáticos
     * @return list<array{codigo: string, mensaje: string}>
     */
    private function alertas(FacturaViatico $f, Viatico $viatico, array $duplicadas): array
    {
        $alertas = [];
        $conRuc = in_array($f->tipo_comprobante, ['factura', 'recibo'], true);

        if ($conRuc && ! RucEcuador::esValido($f->ruc_proveedor)) {
            $alertas[] = ['codigo' => 'ruc', 'mensaje' => "El RUC «{$f->ruc_proveedor}» no es un RUC ecuatoriano válido."];
        }

        if (! $f->fecha_factura) {
            $alertas[] = ['codigo' => 'fecha', 'mensaje' => 'El comprobante no tiene fecha.'];
        } elseif ($viatico->datetime_salida && $viatico->datetime_llegada) {
            // Desde la salida hasta 5 días calendario después del regreso: el
            // mismo período que admite el formulario de comprobantes, porque
            // hay facturas —el hotel, un peaje de vuelta— que se emiten al
            // regresar o en los días siguientes. Decidido con el usuario.
            $desde = $viatico->datetime_salida->copy()->startOfDay();
            $hasta = $viatico->datetime_llegada->copy()->startOfDay()->addDays(self::DIAS_TRAS_EL_REGRESO);

            if ($f->fecha_factura->lt($desde) || $f->fecha_factura->gt($hasta)) {
                $alertas[] = [
                    'codigo'  => 'fecha',
                    'mensaje' => "La fecha {$f->fecha_factura->format('d/m/Y')} está fuera del período válido "
                        . "({$desde->format('d/m/Y')} – {$hasta->format('d/m/Y')}, hasta "
                        . self::DIAS_TRAS_EL_REGRESO . ' días después del regreso).',
                ];
            }
        }

        $clave = $this->claveDuplicado($f);
        if ($clave !== null && isset($duplicadas[$clave])) {
            $alertas[] = ['codigo' => 'duplicado', 'mensaje' => "La misma factura ya se presentó en {$duplicadas[$clave]}."];
        }

        return $alertas;
    }

    /**
     * Las facturas de la lista que aparecen con el mismo RUC y número en otra
     * liquidación. Una sola consulta para toda la lista.
     *
     * @param Collection<int, FacturaViatico> $facturas
     * @return array<string, string>
     */
    private function duplicadas(Collection $facturas): array
    {
        $claves = $facturas->map(fn (FacturaViatico $f) => $this->claveDuplicado($f))->filter()->unique();

        if ($claves->isEmpty()) {
            return [];
        }

        return FacturaViatico::query()
            ->join('liquidaciones_viatico', 'liquidaciones_viatico.id', '=', 'facturas_viatico.liquidacion_viatico_id')
            ->join('viaticos', 'viaticos.id', '=', 'liquidaciones_viatico.viatico_id')
            ->whereNull('viaticos.deleted_at')
            ->whereNotIn('facturas_viatico.liquidacion_viatico_id', $facturas->pluck('liquidacion_viatico_id')->unique())
            ->whereIn(DB::raw("facturas_viatico.ruc_proveedor || '|' || facturas_viatico.numero_factura"), $claves->values())
            ->get(['facturas_viatico.ruc_proveedor', 'facturas_viatico.numero_factura', 'viaticos.codigo_viatico'])
            ->groupBy(fn ($fila) => "{$fila->ruc_proveedor}|{$fila->numero_factura}")
            ->map(fn (Collection $filas) => $filas->pluck('codigo_viatico')->unique()->join(', '))
            ->all();
    }

    private function claveDuplicado(FacturaViatico $f): ?string
    {
        return $f->ruc_proveedor && $f->numero_factura
            ? "{$f->ruc_proveedor}|{$f->numero_factura}"
            : null;
    }

    /** @param array<string, mixed> $datos */
    private function identidad(array $datos): string
    {
        return collect(self::CAMPOS_IDENTIDAD)
            ->map(fn (string $campo) => match ($campo) {
                'monto'         => number_format((float) ($datos[$campo] ?? 0), 2, '.', ''),
                'fecha_factura' => isset($datos[$campo]) && $datos[$campo]
                    ? substr((string) ($datos[$campo] instanceof \DateTimeInterface ? $datos[$campo]->format('Y-m-d') : $datos[$campo]), 0, 10)
                    : '',
                default         => trim((string) ($datos[$campo] ?? '')),
            })
            ->join('|');
    }
}

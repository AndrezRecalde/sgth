<?php
namespace App\Services\Viatico;

use App\Contracts\Viatico\ViaticoServiceInterface;
use App\Enums\EstadoViatico;
use App\Exceptions\ReglaNegocioException;
use App\Helpers\DiasHabilesHelper;
use App\Models\Expediente\Servidor;
use App\Models\Viatico\ActividadLiquidacion;
use App\Models\Viatico\FacturaViatico;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use App\Models\Viatico\ViaticoHistorialEstado;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ViaticoService implements ViaticoServiceInterface
{
    use DiasHabilesHelper;

    /** Días hábiles que tiene el servidor, desde que regresa, para liquidar. */
    public const DIAS_HABILES_PARA_LIQUIDAR = 4;

    public function __construct(
        private readonly CalculoViaticoService $calculo,
    ) {}

    public function solicitar(
        int $servidorId,
        array $datos,
        int $userId
    ): Viatico {
        if ($this->verificarBloqueo($servidorId)) {
            throw new ReglaNegocioException(
                'El servidor tiene bloqueada la solicitud de ' .
                'nuevos viáticos por mantener liquidaciones ' .
                'pendientes fuera del plazo de ' . self::DIAS_HABILES_PARA_LIQUIDAR . ' días hábiles.'
            );
        }

        $servidor = Servidor::with('puesto')->findOrFail($servidorId);
        $zona     = $datos['zona'];

        $datetimeSalida  = Carbon::parse($datos['datetime_salida']);
        $datetimeLlegada = Carbon::parse($datos['datetime_llegada']);

        $this->calculo->asegurarPernocte($datetimeSalida, $datetimeLlegada);

        $noches = $this->calculo->noches($datetimeSalida, $datetimeLlegada);

        // En el exterior el monto queda en 0 hasta que Financiero apruebe con
        // el coeficiente del país.
        $montoCalculado = $this->calculo->derecho($servidor, $zona, $noches);

        return DB::transaction(function () use (
            $servidorId, $datos, $userId,
            $montoCalculado, $datetimeSalida,
            $datetimeLlegada, $noches
        ) {
            $viatico = Viatico::create([
                'servidor_id'        => $servidorId,
                'zona'               => $datos['zona'],
                'fecha_solicitud'    => now()->toDateString(),
                'datetime_salida'    => $datetimeSalida,
                'datetime_llegada'   => $datetimeLlegada,
                'noches'             => $noches,
                'tipo_viaje'         => $datos['tipo_viaje']     ?? null,
                'pais_destino'       => $datos['pais_destino']   ?? null,
                'justificacion'      => $datos['justificacion'],
                'estado'             => EstadoViatico::SOLICITADO,
                'monto_calculado'    => $montoCalculado,
                'monto_anticipo'     => 0.00,
                'modalidad_anticipo' => $datos['modalidad_anticipo'] ?? 'total',
                'created_by'         => $userId,
            ]);

            ViaticoHistorialEstado::create([
                'viatico_id'   => $viatico->id,
                'estado_nuevo' => EstadoViatico::SOLICITADO->value,
                'usuario_id'   => $userId,
            ]);

            return $viatico;
        });
    }

    public function liquidar(
        int $viaticoId,
        array $datos,
        int $userId
    ): LiquidacionViatico {
        return DB::transaction(function () use ($viaticoId, $datos, $userId) {
            $viatico = Viatico::lockForUpdate()->findOrFail($viaticoId);

            if ($viatico->estado !== EstadoViatico::PENDIENTE_LIQUIDACION) {
                throw new ReglaNegocioException(
                    'El viático no se encuentra en estado ' .
                    'pendiente de liquidación.'
                );
            }

            $fechaRetorno = isset($datos['fecha_retorno'])
                ? Carbon::parse($datos['fecha_retorno'])
                : Carbon::parse($viatico->datetime_llegada);

            $facturasPayload    = $datos['facturas']    ?? [];
            $actividadesPayload = $datos['actividades'] ?? [];

            $liquidacion = LiquidacionViatico::create([
                'viatico_id'        => $viaticoId,
                'total_facturas'    => 0,
                'fecha_retorno'     => $fechaRetorno,
                'fecha_liquidacion' => now()->toDateString(),
                'observaciones'     => $datos['observaciones'] ?? null,
                'created_by'        => $userId,
            ]);

            // Crear facturas
            foreach ($facturasPayload as $facturaData) {
                FacturaViatico::create([
                    'liquidacion_viatico_id' => $liquidacion->id,
                    'categoria_factura_id'   => $facturaData['categoria_factura_id'] ?? null,
                    'tipo_comprobante'       => $facturaData['tipo_comprobante']     ?? 'factura',
                    'numero_factura'         => $facturaData['numero_factura']       ?? null,
                    'numero_ticket'          => $facturaData['numero_ticket']        ?? null,
                    'fecha_factura'          => $facturaData['fecha_factura']        ?? null,
                    'ruc_proveedor'          => $facturaData['ruc_proveedor']        ?? null,
                    'nombre_proveedor'       => $facturaData['nombre_proveedor'],
                    'detalle'                => $facturaData['detalle']              ?? null,
                    'monto'                  => $facturaData['monto'],
                ]);
            }

            // Crear actividades del informe
            foreach ($actividadesPayload as $i => $actividadData) {
                ActividadLiquidacion::create([
                    'liquidacion_viatico_id' => $liquidacion->id,
                    'fecha'                  => $actividadData['fecha'],
                    'hora_inicio'            => $actividadData['hora_inicio'],
                    'hora_fin'               => $actividadData['hora_fin'],
                    'descripcion'            => $actividadData['descripcion'],
                    'lugar'                  => $actividadData['lugar'],
                    'orden'                  => $i + 1,
                ]);
            }

            // Con los comprobantes ya guardados: lo justificado dentro del
            // 70 %, lo reconocido y el saldo salen de la misma fórmula que usan
            // la pantalla y el comprobante contable.
            $this->calculo->guardarEn($liquidacion, $viatico);

            $viatico->estado     = EstadoViatico::LIQUIDADO;
            $viatico->updated_by = $userId;
            $viatico->save();

            ViaticoHistorialEstado::create([
                'viatico_id'      => $viaticoId,
                'estado_anterior' => EstadoViatico::PENDIENTE_LIQUIDACION->value,
                'estado_nuevo'    => EstadoViatico::LIQUIDADO->value,
                'usuario_id'      => $userId,
            ]);

            return $liquidacion->load('actividades', 'detallesFactura');
        });
    }

    public function verificarBloqueo(int $servidorId): bool
    {
        $viaticosPendientes = Viatico::where('servidor_id', $servidorId)
            ->where('estado', EstadoViatico::PENDIENTE_LIQUIDACION)
            ->get();

        foreach ($viaticosPendientes as $v) {
            if (!$v->datetime_llegada) continue;

            if (now()->gt($this->fechaLimiteLiquidacion($v))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hasta cuándo puede liquidarse: 4 días hábiles después del regreso, a la
     * misma hora. La usan el bloqueo y la bandeja de Financiero, que tienen que
     * decir lo mismo.
     *
     * Eran 5; Gestión Financiera confirmó 4 el 2026-09-15.
     * `calcularDiasHabiles()` empieza a contar el día siguiente al regreso: el
     * día en que vuelve no cuenta.
     */
    public function fechaLimiteLiquidacion(Viatico $viatico): Carbon
    {
        return $this->calcularDiasHabiles(
            Carbon::parse($viatico->datetime_llegada)->copy(),
            self::DIAS_HABILES_PARA_LIQUIDAR
        );
    }

}

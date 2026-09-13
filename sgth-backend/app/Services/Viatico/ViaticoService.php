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
use App\Models\Viatico\TarifaViatico;
use App\Models\Viatico\Viatico;
use App\Models\Viatico\ViaticoHistorialEstado;
use App\Models\Viatico\ViaticoServidor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ViaticoService implements ViaticoServiceInterface
{
    use DiasHabilesHelper;

    public function solicitar(
        int $servidorId,
        array $datos,
        int $userId
    ): Viatico {
        if ($this->verificarBloqueo($servidorId)) {
            throw new ReglaNegocioException(
                'El servidor tiene bloqueada la solicitud de ' .
                'nuevos viáticos por mantener liquidaciones ' .
                'pendientes fuera del plazo legal de 5 días hábiles.'
            );
        }

        $servidor = Servidor::with('puesto')->findOrFail($servidorId);
        $zona     = $datos['zona'];

        $datetimeSalida  = Carbon::parse($datos['datetime_salida']);
        $datetimeLlegada = Carbon::parse($datos['datetime_llegada']);

        // Opción B: días calendario incluyendo día de regreso
        // Solo fechas, sin importar la hora
        $totalDias = (float) $datetimeSalida
            ->copy()->startOfDay()
            ->diffInDays($datetimeLlegada->copy()->startOfDay()) + 1;

        // Para exterior el monto viene manual
        if ($zona === 'exterior') {
            $montoCalculado = (float) ($datos['monto_calculado'] ?? 0.00);
        } else {
            $montoCalculado = $this->calcularMonto(
                $servidor,
                $zona,
                $totalDias,
                $datetimeSalida,
                $datetimeLlegada
            );
        }

        return DB::transaction(function () use (
            $servidorId, $datos, $userId,
            $montoCalculado, $datetimeSalida,
            $datetimeLlegada, $totalDias
        ) {
            $viatico = Viatico::create([
                'servidor_id'        => $servidorId,
                'zona'               => $datos['zona'],
                'fecha_solicitud'    => now()->toDateString(),
                'datetime_salida'    => $datetimeSalida,
                'datetime_llegada'   => $datetimeLlegada,
                'total_dias'         => $totalDias,
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

            // Registrar servidor titular
            ViaticoServidor::create([
                'viatico_id'  => $viatico->id,
                'servidor_id' => $servidorId,
                'es_titular'  => true,
            ]);

            // Servidores acompañantes
            foreach ($datos['servidores_acompanantes'] ?? [] as $sid) {
                if ((int) $sid === $servidorId) continue;
                ViaticoServidor::create([
                    'viatico_id'  => $viatico->id,
                    'servidor_id' => (int) $sid,
                    'es_titular'  => false,
                ]);
            }

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

            $facturasPayload  = $datos['facturas']    ?? [];
            $actividadesPayload = $datos['actividades'] ?? [];
            $totalFacturas    = collect($facturasPayload)->sum('monto');
            $montoAsignado    = (float) ($viatico->monto_calculado ?? 0.00);
            $montoAnticipo    = (float) ($viatico->monto_anticipo ?? 0.00);

            // Solo H&A cuenta para justificar el 70%
            $idsViatico = \App\Models\Viatico\CategoriaFactura
                ::where('grupo', 'viatico')
                ->pluck('id')
                ->toArray();

            $totalHospAli = collect($facturasPayload)
                ->whereIn('categoria_factura_id', $idsViatico)
                ->sum('monto');

            $modalidad = $viatico->modalidad_anticipo instanceof \BackedEnum
                ? $viatico->modalidad_anticipo->value
                : (string) $viatico->modalidad_anticipo;

            if ($modalidad === 'sin_anticipo') {
                // Sin anticipo: no debe nada,
                // la institución le paga lo justificado + 30%
                $diferenciaDevolver = 0;
            } else {
                // Con anticipo (70%):
                // debe justificar el monto del anticipo
                if ($totalHospAli >= $montoAnticipo ||
                    $totalFacturas >= $montoAsignado) {
                    $diferenciaDevolver = 0;
                } else {
                    $diferenciaDevolver = round(
                        $montoAnticipo - $totalHospAli, 2
                    );
                }
            }

            $liquidacion = LiquidacionViatico::create([
                'viatico_id'          => $viaticoId,
                'total_facturas'      => $totalFacturas,
                'diferencia_devolver' => $diferenciaDevolver,
                'fecha_retorno'       => $fechaRetorno,
                'fecha_liquidacion'   => now()->toDateString(),
                'observaciones'       => $datos['observaciones'] ?? null,
                'created_by'          => $userId,
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

            // Cinco, no cuatro: calcularDiasHabiles() cuenta a partir del día
            // siguiente al retorno, así que pedirle 4 dejaba el plazo en
            // cuatro días hábiles y bloqueaba al servidor un día antes de lo
            // que permite la norma — la misma que cita el mensaje de error.
            $fechaLimite = $this->calcularDiasHabiles(
                Carbon::parse($v->datetime_llegada)->copy(), 5
            );

            if (now()->gt($fechaLimite)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Un desplazamiento de menos de 10 horas que no obliga a pernoctar paga
     * subsistencia, no viático: la diferencia es que el viático cubre el
     * alojamiento y la subsistencia solo la alimentación.
     *
     * El catálogo de tarifas ya distinguía las dos —subsistencia está sembrada
     * a la mitad para cada zona y nivel— pero el cálculo pedía siempre
     * 'con_pernocte', así que una comisión de ocho horas se pagaba como si el
     * servidor hubiera dormido fuera.
     */
    private function aplicaSubsistencia(
        ?Carbon $salida,
        ?Carbon $llegada
    ): bool {
        if (!$salida || !$llegada) {
            return false;
        }

        // Cruzar la medianoche implica pernoctar, dure lo que dure.
        if (!$salida->isSameDay($llegada)) {
            return false;
        }

        return $salida->diffInHours($llegada) < 10;
    }

    private function calcularMonto(
        Servidor $servidor,
        string $zona,
        float $totalDias = 1,
        ?Carbon $datetimeSalida = null,
        ?Carbon $datetimeLlegada = null
    ): float {
        $denominacion = strtolower(
            $servidor->puesto?->cargo?->nombre ?? ''
        );
        $esAutoridad = str_contains($denominacion, 'director')
                    || str_contains($denominacion, 'prefecto')
                    || str_contains($denominacion, 'coordinador')
                    || str_contains($denominacion, 'secretario');
        $nivel = $esAutoridad ? 'autoridad' : 'servidor';

        $subsistencia = $this->aplicaSubsistencia($datetimeSalida, $datetimeLlegada);
        $tipoTarifa   = $subsistencia ? 'subsistencia' : 'con_pernocte';

        $tarifa = TarifaViatico::where('zona', $zona)
            ->where('nivel', $nivel)
            ->where('tipo_tarifa', $tipoTarifa)
            ->first();

        if (!$tarifa) {
            throw new ReglaNegocioException(
                "No se encontró tarifa para: zona={$zona}, " .
                "nivel={$nivel}, tipo={$tipoTarifa}. Verifique las tarifas."
            );
        }

        // La subsistencia se paga una sola vez: por definición no hay más de
        // un día que cubrir.
        $dias = $subsistencia ? 1 : $totalDias;

        return round((float) $tarifa->valor_diario * $dias, 2);
    }
}

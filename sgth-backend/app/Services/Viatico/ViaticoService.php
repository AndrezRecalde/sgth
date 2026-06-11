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
                $totalDias
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

    public function validarParaSolicitar(int $viaticoId): void
    {
        $viatico = Viatico::with('destinos')->findOrFail($viaticoId);

        if ($viatico->destinos->isEmpty()) {
            throw new ReglaNegocioException(
                'El viático debe tener al menos un destino registrado ' .
                'antes de ser solicitado.'
            );
        }

        if ($viatico->tieneAutorizacionesPendientes()) {
            throw new ReglaNegocioException(
                'El viático no puede avanzar porque tiene ' .
                'autorizaciones de vuelo en estado pendiente.'
            );
        }
    }

    public function liquidar(
        int $viaticoId,
        array $datos,
        int $userId
    ): LiquidacionViatico {
        $viatico = Viatico::findOrFail($viaticoId);

        if ($viatico->estado !== EstadoViatico::PENDIENTE_LIQUIDACION) {
            throw new ReglaNegocioException(
                'El viático no se encuentra en estado ' .
                'pendiente de liquidación.'
            );
        }

        $fechaRetorno = isset($datos['fecha_retorno'])
            ? Carbon::parse($datos['fecha_retorno'])
            : Carbon::parse($viatico->datetime_llegada);

        return DB::transaction(function () use (
            $viatico, $viaticoId, $datos, $fechaRetorno, $userId
        ) {
            $facturasPayload  = $datos['facturas']    ?? [];
            $actividadesPayload = $datos['actividades'] ?? [];
            $totalFacturas    = collect($facturasPayload)->sum('monto');
            $montoAsignado    = (float) ($viatico->monto_calculado ?? 0.00);
            $montoAnticipo    = (float) ($viatico->monto_anticipo ?? 0.00);
            $monto70          = round($montoAsignado * 0.70, 2);

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

            // Actualizar servidores acompañantes si vienen en liquidación
            if (!empty($datos['servidores_acompanantes'])) {
                // Eliminar acompañantes anteriores (no el titular)
                ViaticoServidor::where('viatico_id', $viaticoId)
                    ->where('es_titular', false)
                    ->delete();

                foreach ($datos['servidores_acompanantes'] as $sid) {
                    $servidorTitularId = $viatico->servidor_id;
                    if ((int) $sid === $servidorTitularId) continue;
                    ViaticoServidor::create([
                        'viatico_id'  => $viaticoId,
                        'servidor_id' => (int) $sid,
                        'es_titular'  => false,
                    ]);
                }
            }

            $viatico->estado     = EstadoViatico::LIQUIDADO;
            $viatico->updated_by = $userId;
            $viatico->save();

            return $liquidacion->load('actividades', 'detallesFactura');
        });
    }

    public function contabilizar(
        int $viaticoId,
        int $userId
    ): LiquidacionViatico {
        $viatico = Viatico::with('liquidacion')->findOrFail($viaticoId);

        if ($viatico->estado !== EstadoViatico::LIQUIDADO) {
            throw new ReglaNegocioException(
                'Solo se pueden contabilizar viáticos ' .
                'en estado liquidado.'
            );
        }

        if (!$viatico->liquidacion) {
            throw new ReglaNegocioException(
                'El viático no tiene liquidación registrada.'
            );
        }

        $jefeService = app(
            \App\Services\Viatico\JefeFinancieroService::class
        );
        $jefe = $jefeService->obtenerJefeFinanciero();

        return DB::transaction(function () use (
            $viatico, $userId, $jefe
        ) {
            $viatico->liquidacion->update([
                'jefe_financiero_id'    => $jefe['user_id'],
                'cargo_jefe_financiero' => $jefe['cargo'],
                'contabilizado_por'     => $userId,
                'fecha_contabilizacion' => now()->toDateString(),
            ]);

            $viatico->update([
                'estado'     => EstadoViatico::CONTABILIZADO,
                'updated_by' => $userId,
            ]);

            return $viatico->liquidacion->fresh();
        });
    }

    public function aprobar(
        int $viaticoId,
        array $datos = []
    ): Viatico {
        $viatico = Viatico::with('servidor.puesto')
            ->findOrFail($viaticoId);

        if ($viatico->estado !== EstadoViatico::SOLICITADO) {
            throw new ReglaNegocioException(
                'Solo se pueden aprobar viáticos en estado solicitado.'
            );
        }

        $zonaValue = $viatico->zona instanceof \BackedEnum
            ? $viatico->zona->value
            : (string) $viatico->zona;

        if ($zonaValue === 'exterior'
            && isset($datos['coeficiente_exterior'])
            && (float) $datos['coeficiente_exterior'] > 0
        ) {
            $coeficiente = (float) $datos['coeficiente_exterior'];
            $paisDestino = $datos['pais_destino'] ?? $viatico->pais_destino;

            // Determinar tarifa base por rol_puesto
            $rolPuesto = $viatico->servidor?->puesto?->rol_puesto ?? '';
            $tarifaBase = $rolPuesto === 'dignatario'
                ? 220.00
                : 185.00;

            $montoCalculado = round(
                $tarifaBase * $coeficiente * (float) $viatico->total_dias,
                2
            );

            $viatico->update([
                'estado'               => EstadoViatico::APROBADO,
                'monto_calculado'      => $montoCalculado,
                'coeficiente_exterior' => $coeficiente,
                'pais_destino'         => $paisDestino,
            ]);
        } else {
            $viatico->update([
                'estado' => EstadoViatico::APROBADO,
            ]);
        }

        return $viatico->fresh();
    }

    public function entregarAnticipo(int $viaticoId): Viatico
    {
        $viatico = Viatico::findOrFail($viaticoId);

        if ($viatico->estado !== EstadoViatico::APROBADO) {
            throw new ReglaNegocioException(
                'Solo se puede entregar anticipo a viáticos aprobados.'
            );
        }

        // Anticipo = 70% del monto calculado
        $montoAnticipo = round(
            (float) $viatico->monto_calculado * 0.70, 2
        );

        $viatico->update([
            'estado'         => EstadoViatico::CON_ANTICIPO,
            'monto_anticipo' => $montoAnticipo,
        ]);

        return $viatico->fresh();
    }

    public function cancelar(
        int $viaticoId,
        int $userId
    ): Viatico {
        $viatico = Viatico::findOrFail($viaticoId);

        $estadosPermitidos = [
            EstadoViatico::SOLICITADO,
        ];

        if (!in_array($viatico->estado, $estadosPermitidos)) {
            throw new ReglaNegocioException(
                'Solo se puede cancelar una solicitud en estado solicitado.'
            );
        }

        $viatico->update([
            'estado'     => EstadoViatico::CANCELADO,
            'updated_by' => $userId,
        ]);

        return $viatico->fresh();
    }

    public function rechazar(
        int $viaticoId,
        int $userId
    ): Viatico {
        $viatico = Viatico::findOrFail($viaticoId);

        $estadosNoPermitidos = [
            EstadoViatico::CONTABILIZADO,
            EstadoViatico::CANCELADO,
            EstadoViatico::RECHAZADO,
        ];

        if (in_array($viatico->estado, $estadosNoPermitidos)) {
            throw new ReglaNegocioException(
                'No se puede rechazar un viático en este estado.'
            );
        }

        $viatico->update([
            'estado'     => EstadoViatico::RECHAZADO,
            'updated_by' => $userId,
        ]);

        return $viatico->fresh();
    }

    public function devolverCorreccion(
        int $viaticoId,
        int $userId
    ): Viatico {
        $viatico = Viatico::findOrFail($viaticoId);

        if ($viatico->estado !== EstadoViatico::LIQUIDADO) {
            throw new ReglaNegocioException(
                'Solo se puede devolver a corrección
                 un viático en estado liquidado.'
            );
        }

        $viatico->update([
            'estado'     => EstadoViatico::PENDIENTE_LIQUIDACION,
            'updated_by' => $userId,
        ]);

        return $viatico->fresh();
    }

    public function verificarBloqueo(int $servidorId): bool
    {
        $viaticosPendientes = Viatico::where('servidor_id', $servidorId)
            ->where('estado', EstadoViatico::PENDIENTE_LIQUIDACION)
            ->get();

        foreach ($viaticosPendientes as $v) {
            if (!$v->datetime_llegada) continue;

            $fechaLimite = $this->calcularDiasHabiles(
                Carbon::parse($v->datetime_llegada)->copy(), 4
            );

            if (now()->gt($fechaLimite)) {
                return true;
            }
        }

        return false;
    }

    private function calcularMonto(
        Servidor $servidor,
        string $zona,
        float $totalDias = 1
    ): float {
        $denominacion = strtolower(
            $servidor->puesto?->cargo?->nombre ?? ''
        );
        $esAutoridad = str_contains($denominacion, 'director')
                    || str_contains($denominacion, 'prefecto')
                    || str_contains($denominacion, 'coordinador')
                    || str_contains($denominacion, 'secretario');
        $nivel = $esAutoridad ? 'autoridad' : 'servidor';

        $tarifa = TarifaViatico::where('zona', $zona)
            ->where('nivel', $nivel)
            ->where('tipo_tarifa', 'con_pernocte')
            ->first();

        if (!$tarifa) {
            throw new ReglaNegocioException(
                "No se encontró tarifa para: zona={$zona}, " .
                "nivel={$nivel}. Verifique las tarifas."
            );
        }

        return round((float) $tarifa->valor_diario * $totalDias, 2);
    }
}

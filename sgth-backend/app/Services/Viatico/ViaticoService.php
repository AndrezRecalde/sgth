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
                'El servidor tiene bloqueada la solicitud de nuevos ' .
                'viáticos por mantener liquidaciones pendientes fuera ' .
                'del plazo legal de 5 días hábiles.'
            );
        }

        $servidor    = Servidor::with('puesto')->findOrFail($servidorId);
        $fechaInicio = Carbon::parse($datos['fecha_inicio']);
        $fechaFin    = Carbon::parse($datos['fecha_fin']);
        $zona        = $datos['zona'];

        // Para viaje al EXTERIOR el monto viene manual del request
        if ($zona === 'exterior') {
            $montoCalculado = (float) ($datos['monto_calculado'] ?? 0.00);
        } else {
            $montoCalculado = $this->calcularMonto(
                $servidor, $zona, $datos['tipo'], $fechaInicio, $fechaFin
            );
        }

        return DB::transaction(function () use (
            $servidorId, $datos, $userId,
            $fechaInicio, $fechaFin, $montoCalculado
        ) {
            $viatico = Viatico::create([
                'servidor_id'       => $servidorId,
                'comision_id'       => $datos['comision_id']    ?? null,
                'zona'              => $datos['zona'],
                'tipo'              => $datos['tipo'],
                'tipo_viaje'        => $datos['tipo_viaje']     ?? null,
                'pais_destino'      => $datos['pais_destino']   ?? null,
                'fecha_inicio'      => $fechaInicio,
                'fecha_fin'         => $fechaFin,
                'justificacion'     => $datos['justificacion'],
                'estado'            => EstadoViatico::SOLICITADO,
                'monto_calculado'   => $montoCalculado,
                'monto_anticipo'    => 0.00,
                'modalidad_anticipo'=> $datos['modalidad_anticipo'] ?? 'total',
                'created_by'        => $userId,
            ]);

            // Registrar servidor titular
            ViaticoServidor::create([
                'viatico_id'  => $viatico->id,
                'servidor_id' => $servidorId,
                'es_titular'  => true,
            ]);

            // Registrar servidores acompañantes
            foreach ($datos['servidores_acompanantes'] ?? [] as $sid) {
                if ((int) $sid === $servidorId) continue; // evitar duplicado
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

        $fechaRetorno   = Carbon::parse($datos['fecha_retorno']);
        $fechaLimiteLegal = $this->calcularDiasHabiles(
            $viatico->fecha_fin->copy(), 5
        );

        if (now()->gt($fechaLimiteLegal)) {
            // Nota legal: proceso continúa pero podría registrar alerta
        }

        return DB::transaction(function () use (
            $viatico, $viaticoId, $datos, $fechaRetorno, $userId
        ) {
            $facturasPayload  = $datos['facturas']    ?? [];
            $actividadesPayload = $datos['actividades'] ?? [];
            $totalFacturas    = collect($facturasPayload)->sum('monto');
            $anticipoRecibido = (float) ($viatico->monto_anticipo ?? 0.00);
            $diferenciaDevolver = $anticipoRecibido - $totalFacturas;

            $liquidacion = LiquidacionViatico::create([
                'viatico_id'          => $viaticoId,
                'total_facturas'      => $totalFacturas,
                'monto_justificado'   => $totalFacturas,
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
                    'concepto'               => $facturaData['concepto'],
                    'detalle'                => $facturaData['detalle']          ?? null,
                    'numero_factura'         => $facturaData['numero_factura'],
                    'ruc_proveedor'          => $facturaData['ruc_proveedor'],
                    'nombre_proveedor'       => $facturaData['nombre_proveedor'],
                    'monto'                  => $facturaData['monto'],
                    'archivo_ruta'           => $facturaData['archivo_ruta']     ?? null,
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

    public function verificarBloqueo(int $servidorId): bool
    {
        $viaticosPendientes = Viatico::where('servidor_id', $servidorId)
            ->where('estado', EstadoViatico::PENDIENTE_LIQUIDACION)
            ->get();

        foreach ($viaticosPendientes as $v) {
            $fechaLimite = $this->calcularDiasHabiles(
                $v->fecha_fin->copy(), 5
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
        string $tipo,
        Carbon $inicio,
        Carbon $fin
    ): float {
        // Determinar nivel según el puesto
        $denominacion = strtolower(
            $servidor->puesto?->denominacion ?? ''
        );
        $esAutoridad = str_contains($denominacion, 'ministro')
                    || str_contains($denominacion, 'secretario')
                    || str_contains($denominacion, 'prefecto')
                    || str_contains($denominacion, 'director');
        $nivel = $esAutoridad ? 'autoridad' : 'servidor';

        $horasComision = $fin->diffInHours($inicio);
        $diasComision  = $fin->diffInDays($inicio) ?: 1;

        // Determinar tipo de tarifa
        $tipoTarifaBuscar = match($tipo) {
            'sin_pernocte' => $horasComision < 10
                ? 'subsistencia'
                : 'sin_pernocte',
            default => 'con_pernocte',
        };

        $tarifa = TarifaViatico::where('zona', $zona)
            ->where('nivel', $nivel)
            ->where('tipo_tarifa', $tipoTarifaBuscar)
            ->first();

        if (!$tarifa) {
            throw new ReglaNegocioException(
                "No se encontró tarifa para: zona={$zona}, " .
                "nivel={$nivel}, tipo={$tipoTarifaBuscar}. " .
                "Verifique las tarifas en el sistema."
            );
        }

        return $tipo === 'con_pernocte'
            ? (float) $tarifa->valor_diario * $diasComision
            : (float) $tarifa->valor_diario;
    }
}

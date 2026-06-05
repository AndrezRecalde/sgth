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

        $servidor = Servidor::with('puesto')->findOrFail($servidorId);
        $zona     = $datos['zona'];

        // Para exterior el monto viene manual
        $montoCalculado = $zona === 'exterior'
            ? (float) ($datos['monto_calculado'] ?? 0.00)
            : $this->calcularMonto(
                $servidor,
                $zona,
                $datos['tipo_calculo'] ?? 'con_pernocte',
                null,
                null
              );

        return DB::transaction(function () use (
            $servidorId, $datos, $userId, $montoCalculado
        ) {
            $viatico = Viatico::create([
                'servidor_id'        => $servidorId,
                'zona'               => $datos['zona'],
                'fecha_solicitud'    => now()->toDateString(),
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
        string $tipo = 'con_pernocte',
        ?Carbon $inicio = null,
        ?Carbon $fin = null
    ): float {
        // Nivel según denominación del puesto
        $denominacion = strtolower(
            $servidor->puesto?->denominacion ?? ''
        );
        $esAutoridad = str_contains($denominacion, 'ministro')
                    || str_contains($denominacion, 'secretario')
                    || str_contains($denominacion, 'prefecto')
                    || str_contains($denominacion, 'director');
        $nivel = $esAutoridad ? 'autoridad' : 'servidor';

        // Sin fechas → calcular por 1 día como referencia
        $diasComision = ($inicio && $fin)
            ? ($fin->diffInDays($inicio) ?: 1)
            : 1;

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

        return (float) $tarifa->valor_diario * $diasComision;
    }
}

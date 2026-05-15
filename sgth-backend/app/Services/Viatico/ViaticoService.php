<?php

namespace App\Services\Viatico;

use App\Contracts\Viatico\ViaticoServiceInterface;
use App\Enums\EstadoViatico;
use App\Exceptions\ReglaNegocioException;
use App\Helpers\DiasHabilesHelper;
use App\Models\Expediente\Servidor;
use App\Models\Viatico\FacturaViatico;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\TarifaViatico;
use App\Models\Viatico\Viatico;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ViaticoService implements ViaticoServiceInterface
{
    use DiasHabilesHelper;

    public function solicitar(int $servidorId, array $datos, int $userId): Viatico
    {
        if ($this->verificarBloqueo($servidorId)) {
            throw new ReglaNegocioException('El servidor tiene bloqueada la solicitud de nuevos viáticos por mantener liquidaciones pendientes fuera del plazo legal de 5 días hábiles.');
        }

        $servidor = Servidor::with('puesto')->findOrFail($servidorId);
        
        $fechaInicio = Carbon::parse($datos['fecha_inicio']);
        $fechaFin = Carbon::parse($datos['fecha_fin']);

        $montoCalculado = $this->calcularMonto($servidor, $datos['zona'], $datos['tipo'], $fechaInicio, $fechaFin);

        return Viatico::create([
            'servidor_id'      => $servidorId,
            'comision_id'      => $datos['comision_id'] ?? null,
            'zona'             => $datos['zona'],
            'tipo'             => $datos['tipo'],
            'fecha_inicio'     => $fechaInicio,
            'fecha_fin'        => $fechaFin,
            'justificacion'    => $datos['justificacion'],
            'estado'           => EstadoViatico::SOLICITADO,
            'monto_calculado'  => $montoCalculado,
            'monto_anticipo'   => 0.00, // Se define en etapas posteriores
            'created_by'       => $userId,
        ]);
    }

    public function validarParaSolicitar(int $viaticoId): void
    {
        $viatico = Viatico::with('destinos')->findOrFail($viaticoId);

        if ($viatico->destinos->isEmpty()) {
            throw new ReglaNegocioException('El viático debe tener al menos un destino registrado antes de ser solicitado.');
        }

        if ($viatico->tieneAutorizacionesPendientes()) {
            throw new ReglaNegocioException('El viático no puede avanzar porque tiene autorizaciones de vuelo en estado pendiente.');
        }
    }

    public function liquidar(int $viaticoId, array $datos, int $userId): LiquidacionViatico
    {
        $viatico = Viatico::findOrFail($viaticoId);

        if ($viatico->estado !== EstadoViatico::PENDIENTE_LIQUIDACION) {
            throw new ReglaNegocioException('El viático no se encuentra en estado pendiente de liquidación.');
        }

        $fechaRetorno = Carbon::parse($datos['fecha_retorno']);
        $fechaLimiteLegal = $this->calcularDiasHabiles($viatico->fecha_fin->copy(), 5);

        if (now()->gt($fechaLimiteLegal)) {
            // Nota legal: Aquí se podrían aplicar multas, pero el proceso debe continuar
        }

        return DB::transaction(function () use ($viatico, $viaticoId, $datos, $fechaRetorno, $userId) {
            
            $facturasPayload = $datos['facturas'] ?? [];
            $totalFacturas = collect($facturasPayload)->sum('monto');
            
            $anticipoRecibido = $viatico->monto_anticipo ?? 0.00;
            $diferenciaDevolver = $anticipoRecibido - $totalFacturas;

            $liquidacion = LiquidacionViatico::create([
                'viatico_id'          => $viaticoId,
                'total_facturas'      => $totalFacturas,
                'monto_justificado'   => $totalFacturas, // Todo se justifica con facturas en este nuevo modelo
                'diferencia_devolver' => $diferenciaDevolver,
                'fecha_retorno'       => $fechaRetorno,
                'fecha_liquidacion'   => now()->toDateString(),
                'observaciones'       => $datos['observaciones'] ?? null,
                'created_by'          => $userId,
            ]);

            foreach ($facturasPayload as $facturaData) {
                FacturaViatico::create([
                    'liquidacion_viatico_id' => $liquidacion->id,
                    'concepto'               => $facturaData['concepto'],
                    'detalle'                => $facturaData['detalle'] ?? null,
                    'numero_factura'         => $facturaData['numero_factura'],
                    'ruc_proveedor'          => $facturaData['ruc_proveedor'],
                    'nombre_proveedor'       => $facturaData['nombre_proveedor'],
                    'monto'                  => $facturaData['monto'],
                    'archivo_ruta'           => $facturaData['archivo_ruta'] ?? null,
                ]);
            }

            $viatico->estado = EstadoViatico::LIQUIDADO;
            $viatico->updated_by = $userId;
            $viatico->save();

            return $liquidacion;
        });
    }

    public function verificarBloqueo(int $servidorId): bool
    {
        $viaticosPendientes = Viatico::where('servidor_id', $servidorId)
            ->where('estado', EstadoViatico::PENDIENTE_LIQUIDACION)
            ->get();

        foreach ($viaticosPendientes as $v) {
            $fechaLimite = $this->calcularDiasHabiles($v->fecha_fin->copy(), 5);
            if (now()->gt($fechaLimite)) {
                return true; // Existe un viático vencido sin liquidar
            }
        }

        return false;
    }

    private function calcularMonto(Servidor $servidor, string $zona, string $tipo, Carbon $inicio, Carbon $fin): float
    {
        $esAutoridad = str_contains(strtolower($servidor->puesto?->denominacion ?? ''), 'ministro') || str_contains(strtolower($servidor->puesto?->denominacion ?? ''), 'secretario');
        $nivel = $esAutoridad ? 'autoridad' : 'servidor';

        $horasComision = $fin->diffInHours($inicio);
        $diasComision = $fin->diffInDays($inicio) ?: 1;

        $tipoTarifaBuscar = 'con_pernocte';
        if ($tipo === 'sin_pernocte') {
            $tipoTarifaBuscar = $horasComision < 10 ? 'subsistencia' : 'sin_pernocte';
        }

        $tarifa = TarifaViatico::where('zona', $zona)
                                    ->where('nivel', $nivel)
                                    ->where('tipo_tarifa', $tipoTarifaBuscar)
                                    ->first();
                                    
        if (!$tarifa) {
            throw new ReglaNegocioException("No se encontró tarifa definida en el MRL para la zona {$zona}, nivel {$nivel}, tipo {$tipoTarifaBuscar}.");
        }

        if ($tipo === 'con_pernocte') {
            return $tarifa->valor_diario * $diasComision;
        }

        return $tarifa->valor_diario;
    }
}

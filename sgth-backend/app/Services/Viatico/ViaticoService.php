<?php

namespace App\Services\Viatico;

use App\Contracts\Viatico\ViaticoServiceInterface;
use App\Enums\EstadoViatico;
use App\Enums\ZonaViatico;
use App\Exceptions\ReglaNegocioException;
use App\Helpers\DiasHabilesHelper;
use App\Models\Expediente\Servidor;
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

        DB::beginTransaction();
        try {
            $totalFacturas = collect($datos['facturas'] ?? [])->sum('monto');
            // Regla general MRL: Se justifica el 70% con facturas, el 30% no requiere justificativo
            // Si el anticipo fue mayor a lo justificado + 30%, hay diferencia a devolver
            $baseCalculo = $viatico->monto_anticipo;
            $montoMaximoJustificar = $baseCalculo * 0.70;
            $montoJustificado = min($totalFacturas, $montoMaximoJustificar);
            $montoExento = $baseCalculo * 0.30;
            
            $montoLiquidar = $montoJustificado + $montoExento;
            $diferenciaDevolver = $baseCalculo - $montoLiquidar;

            $liquidacion = LiquidacionViatico::create([
                'viatico_id'          => $viaticoId,
                'facturas'            => $datos['facturas'] ?? [],
                'total_facturas'      => $totalFacturas,
                'monto_justificado'   => $montoJustificado,
                'diferencia_devolver' => $diferenciaDevolver > 0 ? $diferenciaDevolver : 0.00,
                'fecha_retorno'       => $fechaRetorno,
                'fecha_liquidacion'   => now()->toDateString(),
                'observaciones'       => $datos['observaciones'] ?? null,
                'created_by'          => $userId,
            ]);

            $viatico->estado = EstadoViatico::LIQUIDADO;
            $viatico->updated_by = $userId;
            $viatico->save();

            DB::commit();
            return $liquidacion;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function verificarBloqueo(int $servidorId): bool
    {
        // Bloqueo: si tiene un viático pendiente de liquidación donde hayan pasado más de 5 días hábiles desde el retorno/fin.
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
        // Determinar nivel jerárquico
        $esAutoridad = str_contains(strtolower($servidor->puesto?->denominacion ?? ''), 'ministro') || str_contains(strtolower($servidor->puesto?->denominacion ?? ''), 'secretario');
        $nivel = $esAutoridad ? 'autoridad' : 'servidor';

        $horasComision = $fin->diffInHours($inicio);
        $diasComision = $fin->diffInDays($inicio) ?: 1; // Mínimo 1 para el multiplicador si pasa de 1 día

        $tipoTarifaBuscar = 'con_pernocte';
        if ($tipo === 'sin_pernocte') {
            $tipoTarifaBuscar = $horasComision < 10 ? 'subsistencia' : 'sin_pernocte';
        }

        $queryTarifa = TarifaViatico::where('zona', $zona)
                                    ->where('nivel', $nivel)
                                    ->where('tipo_tarifa', $tipoTarifaBuscar);
                                    
        // TODO: Para exterior, el país se debería obtener de la relación destinos_viatico
        // Por ahora omitimos el where pais_destino si no está disponible.
        
        $tarifa = $queryTarifa->first();
        if (!$tarifa) {
            throw new ReglaNegocioException("No se encontró tarifa definida en el MRL para la zona {$zona}, nivel {$nivel}, tipo {$tipoTarifaBuscar}.");
        }

        if ($tipo === 'con_pernocte') {
            return $tarifa->valor_diario * $diasComision;
        }

        return $tarifa->valor_diario;
    }
}

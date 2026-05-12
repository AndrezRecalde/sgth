<?php

namespace App\Services\Disciplinario;

use App\Contracts\Disciplinario\DisciplinarioServiceInterface;
use App\Enums\EstadoSumario;
use App\Enums\TipoSancion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Disciplinario\Sumario;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Asistencia\FeriadoInstitucional;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DisciplinarioService implements DisciplinarioServiceInterface
{
    public function resolverSumario(int $sumarioId, array $datosSancion, int $userId): Sumario
    {
        $sumario = Sumario::findOrFail($sumarioId);

        if ($sumario->estado === EstadoSumario::RESUELTO || $sumario->estado === EstadoSumario::CERRADO) {
            throw new ReglaNegocioException('El sumario ya se encuentra resuelto o cerrado.');
        }

        DB::beginTransaction();
        try {
            $sumario->estado = EstadoSumario::RESUELTO;
            $sumario->fecha_resolucion = now()->toDateString();
            $sumario->updated_by = $userId;
            $sumario->save();

            SancionDisciplinaria::create([
                'sumario_id'       => $sumario->id,
                'tipo_falta'       => $datosSancion['tipo_falta'],
                'tipo_sancion'     => $datosSancion['tipo_sancion'],
                'porcentaje_multa' => $datosSancion['porcentaje_multa'] ?? null,
                'dias_suspension'  => $datosSancion['dias_suspension'] ?? null,
                'fecha_efectiva'   => $datosSancion['fecha_efectiva'] ?? now()->toDateString(),
                'observaciones'    => $datosSancion['observaciones'] ?? null,
                'created_by'       => $userId,
            ]);

            // Regla de Negocio: Si la sanción es Destitución, registrar Egreso.
            if ($datosSancion['tipo_sancion'] === TipoSancion::DESTITUCION->value) {
                $servidor = Servidor::findOrFail($sumario->servidor_id);
                $servidor->estado = false;
                $servidor->save();

                MovimientoPersonal::create([
                    'servidor_id'    => $servidor->id,
                    'tipo'           => 'egreso',
                    'fecha_efectiva' => $datosSancion['fecha_efectiva'] ?? now()->toDateString(),
                    'motivo'         => 'Destitución por sanción disciplinaria en Sumario Administrativo #' . $sumario->id,
                    'created_by'     => $userId,
                ]);
            }

            DB::commit();

            return $sumario;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function controlarPlazosLegales(): void
    {
        $hoy = Carbon::today();

        // 1. Control de Notificación: 3 días hábiles desde apertura
        $sumariosSinNotificar = Sumario::where('estado', EstadoSumario::ABIERTO)
            ->where('notificado_sn', false)
            ->get();

        foreach ($sumariosSinNotificar as $sumario) {
            $fechaLimiteNotificacion = $this->calcularDiasHabiles(Carbon::parse($sumario->fecha_apertura), 3);
            if ($hoy->gt($fechaLimiteNotificacion)) {
                Log::warning("Sumario #{$sumario->id} ha excedido el plazo legal de notificación de 3 días hábiles. Fecha límite era: {$fechaLimiteNotificacion->toDateString()}");
            }
        }

        // 2. Control de Resolución: 10 días hábiles desde el informe
        $sumariosConInforme = Sumario::where('estado', EstadoSumario::CON_INFORME)
            ->whereNotNull('fecha_informe')
            ->get();

        foreach ($sumariosConInforme as $sumario) {
            $fechaLimiteResolucion = $this->calcularDiasHabiles(Carbon::parse($sumario->fecha_informe), 10);
            if ($hoy->gt($fechaLimiteResolucion)) {
                Log::error("ALERTA LEGAL: Sumario #{$sumario->id} ha excedido el plazo de resolución de 10 días hábiles desde el informe. Fecha límite era: {$fechaLimiteResolucion->toDateString()}. Riesgo de caducidad.");
            }
        }
    }

    /**
     * Calcula una fecha futura sumando únicamente días hábiles (excluyendo fines de semana y feriados).
     */
    private function calcularDiasHabiles(Carbon $fechaInicio, int $dias): Carbon
    {
        $fecha = $fechaInicio->copy();
        $diasSumados = 0;

        while ($diasSumados < $dias) {
            $fecha->addDay();

            if ($fecha->isWeekend()) {
                continue;
            }

            $esFeriado = FeriadoInstitucional::esFeriado($fecha)->exists();
            if ($esFeriado) {
                continue;
            }

            $diasSumados++;
        }

        return $fecha;
    }
}

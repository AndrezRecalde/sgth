<?php

namespace App\Services\Nomina;

use App\Contracts\Nomina\NominaServiceInterface;
use App\Enums\EstadoNomina;
use App\Enums\RegimenLaboral;
use App\Enums\TipoConcepto;
use App\Models\Expediente\Servidor;
use App\Models\Nomina\ConceptoNomina;
use App\Models\Nomina\DetalleNomina;
use App\Models\Nomina\Nomina;
use App\Models\Nomina\RolPago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NominaService implements NominaServiceInterface
{
    /**
     * Calcula los ingresos, descuentos y totales para un periodo específico,
     * generando la nómina en estado borrador o en_proceso.
     */
    public function calcularNomina(string $periodo): Nomina
    {
        return DB::transaction(function () use ($periodo) {
            // Analizar el periodo YYYY-MM
            $fechaBase = Carbon::createFromFormat('Y-m', $periodo);
            $fechaInicio = $fechaBase->copy()->startOfMonth();
            $fechaFin = $fechaBase->copy()->endOfMonth();

            // Buscar si ya existe la nómina en estado borrador o crearla
            $nomina = Nomina::firstOrCreate(
                ['periodo' => $periodo],
                [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin'    => $fechaFin,
                    'estado'       => EstadoNomina::BORRADOR,
                ]
            );

            // Si la nómina ya está cerrada, no se puede recalcular
            if ($nomina->estado === EstadoNomina::CERRADA || $nomina->estado === EstadoNomina::PAGADA || $nomina->estado === EstadoNomina::CONTABILIZADA) {
                throw new \Exception("La nómina del período {$periodo} ya se encuentra procesada y no puede ser recalculada.");
            }

            // Cambiamos estado a en proceso mientras se calcula
            $nomina->estado = EstadoNomina::EN_PROCESO;
            $nomina->save();

            // Limpiar cálculos anteriores para este periodo
            DetalleNomina::where('nomina_id', $nomina->id)->delete();
            RolPago::where('nomina_id', $nomina->id)->forceDelete(); // forceDelete porque no lleva softDeletes Detalle, pero RolPago si.

            // Obtener conceptos activos
            $conceptos = ConceptoNomina::where('activo', true)->get();
            $conceptoIessPersonal = $conceptos->where('codigo', 'IESS_PERSONAL')->first();
            $conceptoSueldo = $conceptos->where('codigo', 'SUELDO_BASE')->first();
            $conceptoDecimoTercero = $conceptos->where('codigo', 'DECIMO_TERCERO')->first();
            $conceptoDecimoCuarto = $conceptos->where('codigo', 'DECIMO_CUARTO')->first();

            // Obtener servidores activos
            $servidores = Servidor::where('estado', true)->with('puesto')->get();

            $totalIngresosGlobal = 0;
            $totalDescuentosGlobal = 0;
            $totalNetoGlobal = 0;

            foreach ($servidores as $servidor) {
                // RMU Base desde el puesto
                $rmu = $servidor->puesto ? $servidor->puesto->rmu : 0;
                
                if ($rmu <= 0) {
                    continue; // Saltar si no tiene sueldo base
                }

                $ingresos = 0;
                $descuentos = 0;

                // 1. Ingreso Base (Sueldo)
                $valorSueldo = $rmu;
                $ingresos += $valorSueldo;
                
                if ($conceptoSueldo) {
                    DetalleNomina::create([
                        'nomina_id' => $nomina->id,
                        'servidor_id' => $servidor->id,
                        'concepto_nomina_id' => $conceptoSueldo->id,
                        'valor' => $valorSueldo,
                    ]);
                }

                // 2. Beneficios según régimen
                if ($servidor->regimen_laboral === RegimenLaboral::CODIGO_TRABAJO) {
                    // Décimo Tercero proporcional
                    $decimoTercero = round($valorSueldo / 12, 2);
                    $ingresos += $decimoTercero;
                    if ($conceptoDecimoTercero) {
                        DetalleNomina::create([
                            'nomina_id' => $nomina->id,
                            'servidor_id' => $servidor->id,
                            'concepto_nomina_id' => $conceptoDecimoTercero->id,
                            'valor' => $decimoTercero,
                        ]);
                    }

                    // Décimo Cuarto proporcional (SBU Ecuador 2026 estimado 460 / 12)
                    $sbu = 460.00; 
                    $decimoCuarto = round($sbu / 12, 2);
                    $ingresos += $decimoCuarto;
                    if ($conceptoDecimoCuarto) {
                        DetalleNomina::create([
                            'nomina_id' => $nomina->id,
                            'servidor_id' => $servidor->id,
                            'concepto_nomina_id' => $conceptoDecimoCuarto->id,
                            'valor' => $decimoCuarto,
                        ]);
                    }
                }

                // 3. Descuentos: IESS Personal (9.45% por lo general)
                $iessPorcentaje = $conceptoIessPersonal ? $conceptoIessPersonal->porcentaje : 9.45;
                $descuentoIess = round($valorSueldo * ($iessPorcentaje / 100), 2);
                $descuentos += $descuentoIess;

                if ($conceptoIessPersonal) {
                    DetalleNomina::create([
                        'nomina_id' => $nomina->id,
                        'servidor_id' => $servidor->id,
                        'concepto_nomina_id' => $conceptoIessPersonal->id,
                        'valor' => $descuentoIess,
                    ]);
                }

                // 4. Descuentos Recurrentes (Préstamos, Multas, etc.)
                $hoy = now()->toDateString();
                $descuentosRecurrentes = \App\Models\Nomina\DescuentoRecurrente::where('servidor_id', $servidor->id)
                    ->where('estado', \App\Enums\EstadoDescuentoRecurrente::ACTIVO)
                    ->where('fecha_inicio', '<=', $hoy)
                    ->where(function ($query) use ($hoy) {
                        $query->whereNull('fecha_fin')
                              ->orWhere('fecha_fin', '>=', $hoy);
                    })
                    ->get();

                foreach ($descuentosRecurrentes as $descuentoRecurrente) {
                    if ($descuentoRecurrente->numero_cuotas_pagadas < $descuentoRecurrente->numero_cuotas_total) {
                        $cuota = $descuentoRecurrente->valor_cuota;
                        $descuentos += $cuota;

                        DetalleNomina::create([
                            'nomina_id' => $nomina->id,
                            'servidor_id' => $servidor->id,
                            'concepto_nomina_id' => $descuentoRecurrente->concepto_nomina_id,
                            'valor' => $cuota,
                            'observacion' => $descuentoRecurrente->referencia_externa ? "Cuota " . ($descuentoRecurrente->numero_cuotas_pagadas + 1) . " Ref: {$descuentoRecurrente->referencia_externa}" : "Cuota " . ($descuentoRecurrente->numero_cuotas_pagadas + 1),
                        ]);

                        $descuentoRecurrente->numero_cuotas_pagadas += 1;

                        if ($descuentoRecurrente->numero_cuotas_pagadas >= $descuentoRecurrente->numero_cuotas_total) {
                            $descuentoRecurrente->estado = \App\Enums\EstadoDescuentoRecurrente::COMPLETADO;
                        }

                        $descuentoRecurrente->save();
                    }
                }

                // Generar Rol de Pago Individual
                $neto = $ingresos - $descuentos;

                RolPago::create([
                    'nomina_id' => $nomina->id,
                    'servidor_id' => $servidor->id,
                    'total_ingresos' => $ingresos,
                    'total_descuentos' => $descuentos,
                    'total_neto' => $neto,
                ]);

                // Acumuladores globales
                $totalIngresosGlobal += $ingresos;
                $totalDescuentosGlobal += $descuentos;
                $totalNetoGlobal += $neto;
            }

            // Actualizar totales en la nómina
            $nomina->total_ingresos = $totalIngresosGlobal;
            $nomina->total_descuentos = $totalDescuentosGlobal;
            $nomina->total_neto = $totalNetoGlobal;
            $nomina->estado = EstadoNomina::BORRADOR; // Vuelve a borrador esperando revisión
            $nomina->save();

            return $nomina;
        });
    }

    /**
     * Cierra de forma definitiva la nómina, congelando los valores y disparando
     * las integraciones (Handoff ERP) y envíos de correo.
     */
    public function cerrarNomina(int $nominaId, int $userId): Nomina
    {
        return DB::transaction(function () use ($nominaId, $userId) {
            $nomina = Nomina::findOrFail($nominaId);

            if ($nomina->estado === EstadoNomina::CERRADA || $nomina->estado === EstadoNomina::PAGADA) {
                throw new \Exception("La nómina ya se encuentra cerrada.");
            }

            $nomina->estado = EstadoNomina::CERRADA;
            $nomina->cerrado_por = $userId;
            $nomina->cerrado_en = now();
            $nomina->save();

            // Aquí se dispararían los Jobs asíncronos en el orden requerido:
            // 1. Dispatch GenerarHandoffErpJob
            \App\Jobs\Nomina\GenerarHandoffErpJob::dispatch($nomina->id);
            
            // 2. Dispatch EnviarRolPagoJob por cada servidor
            foreach($nomina->rolesPago as $rol) {
                \App\Jobs\Nomina\EnviarRolPagoJob::dispatch($rol->id);
            }

            return $nomina;
        });
    }
}

<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoContrato;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use Illuminate\Support\Facades\Log;

/**
 * Detecta contratos de Servicios Profesionales y de servicios ocasionales cuyo
 * plazo venció, y genera la Cesación de Funciones correspondiente en BORRADOR:
 * «Contrato finalizado» para el contrato civil, «Terminación por cumplimiento
 * del plazo» (Reglamento 146 a) para el ocasional. Los ocasionales entraron en
 * la fase 2.1 del diseño de Acciones de Personal (4.3; TH 11): ninguna tarea
 * cesaba al ocasional cuyo plazo pasaba.
 *
 * Si consta que una ocasional está embarazada o en lactancia, el borrador se
 * genera igual —Talento Humano tiene que saber que el plazo venció— pero con el
 * aviso de que la ley la protege.
 *
 * No cierra el vínculo: eso lo hace la acción de personal cuando Talento
 * Humano la revisa y la registra. Aquí solo se levanta la alerta en forma de
 * borrador, que es lo que pidió TH — nada se da de baja sin aprobación.
 */
class ContratoVencidoService
{
    public function __construct(
        private readonly MovimientoPersonalService $movimientoPersonalService,
    ) {
    }

    /**
     * @return array{generadas:list<array{contrato_id:int,servidor_id:int,movimiento_id:int}>, omitidas:list<array{contrato_id:int,motivo:string}>}
     */
    public function generarCesacionesPendientes(?string $hasta = null): array
    {
        $hasta = $hasta ?? now()->toDateString();

        $vencidos = ContratoServidor::with('servidor')
            ->whereIn('tipo_nombramiento', [
                TipoNombramiento::SERVICIOS_PROFESIONALES->value,
                TipoNombramiento::SERVICIOS_OCASIONALES->value,
            ])
            ->where('estado', EstadoContrato::VIGENTE->value)
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', $hasta)
            ->get();

        $generadas = [];
        $omitidas = [];

        foreach ($vencidos as $contrato) {
            $fechaFin = $contrato->fecha_fin->toDateString();
            $ocasional = $contrato->tipo_nombramiento === TipoNombramiento::SERVICIOS_OCASIONALES;
            $causal = $ocasional
                ? SubtipoMovimientoPersonal::FIN_DEL_PLAZO
                : SubtipoMovimientoPersonal::CONTRATO_FINALIZADO;

            if ($this->yaTieneCesacion($contrato->servidor_id, $fechaFin, $causal)) {
                $omitidas[] = [
                    'contrato_id' => $contrato->id,
                    'motivo'      => "Ya existe una cesación por «{$causal->etiqueta()}» para este período.",
                ];

                continue;
            }

            try {
                $movimiento = $this->movimientoPersonalService->registrar($contrato->servidor_id, [
                    'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
                    'subtipo_movimiento' => $causal->value,
                    'descripcion'        => ($ocasional
                        ? 'Terminación del contrato de servicios ocasionales por cumplimiento del plazo '
                        : 'Terminación del contrato de Servicios Profesionales por vencimiento del plazo ')
                        ."el {$fechaFin}. Generada automáticamente para revisión de Talento Humano.",
                    // La observación no se imprime: es para quien revisa.
                    'observacion'        => $ocasional && ProteccionMaternidad::consta($contrato->servidor_id)
                        ? ProteccionMaternidad::aviso()
                        : null,
                    'fecha_efectiva'     => $fechaFin,
                ]);

                $generadas[] = [
                    'contrato_id'   => $contrato->id,
                    'servidor_id'   => $contrato->servidor_id,
                    'movimiento_id' => $movimiento->id,
                ];
            } catch (\Throwable $e) {
                // Un contrato problemático no debe abortar la corrida: se
                // registra y se sigue con el resto.
                $omitidas[] = [
                    'contrato_id' => $contrato->id,
                    'motivo'      => $e->getMessage(),
                ];

                Log::warning(
                    "No se pudo generar la cesación del contrato #{$contrato->id}: {$e->getMessage()}"
                );
            }
        }

        return ['generadas' => $generadas, 'omitidas' => $omitidas];
    }

    /**
     * Idempotencia por período: se compara contra la fecha de vencimiento, así
     * que un servidor recontratado al año siguiente sí genera una cesación
     * nueva cuando ese contrato vence. Las anuladas no cuentan.
     */
    private function yaTieneCesacion(int $servidorId, string $fechaFin, SubtipoMovimientoPersonal $causal): bool
    {
        return MovimientoPersonal::where('servidor_id', $servidorId)
            ->where('tipo_movimiento', TipoMovimientoPersonal::CESACION_FUNCIONES->value)
            ->where('subtipo_movimiento', $causal->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereDate('fecha_efectiva', $fechaFin)
            ->exists();
    }
}

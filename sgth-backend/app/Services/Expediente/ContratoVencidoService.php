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
 * Detecta contratos de Servicios Profesionales cuyo plazo venció y genera la
 * Cesación de Funciones correspondiente en BORRADOR.
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
            ->where('tipo_nombramiento', TipoNombramiento::SERVICIOS_PROFESIONALES->value)
            ->where('estado', EstadoContrato::VIGENTE->value)
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<', $hasta)
            ->get();

        $generadas = [];
        $omitidas = [];

        foreach ($vencidos as $contrato) {
            $fechaFin = $contrato->fecha_fin->toDateString();

            if ($this->yaTieneCesacion($contrato->servidor_id, $fechaFin)) {
                $omitidas[] = [
                    'contrato_id' => $contrato->id,
                    'motivo'      => 'Ya existe una cesación por contrato finalizado para este período.',
                ];

                continue;
            }

            try {
                $movimiento = $this->movimientoPersonalService->registrar($contrato->servidor_id, [
                    'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
                    'subtipo_movimiento' => SubtipoMovimientoPersonal::CONTRATO_FINALIZADO->value,
                    'descripcion'        => 'Terminación del contrato de Servicios Profesionales por '
                        ."vencimiento del plazo el {$fechaFin}. Generada automáticamente para revisión "
                        .'de Talento Humano.',
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
    private function yaTieneCesacion(int $servidorId, string $fechaFin): bool
    {
        return MovimientoPersonal::where('servidor_id', $servidorId)
            ->where('tipo_movimiento', TipoMovimientoPersonal::CESACION_FUNCIONES->value)
            ->where('subtipo_movimiento', SubtipoMovimientoPersonal::CONTRATO_FINALIZADO->value)
            ->where('estado', '!=', EstadoAccionPersonal::ANULADA->value)
            ->whereDate('fecha_efectiva', $fechaFin)
            ->exists();
    }
}

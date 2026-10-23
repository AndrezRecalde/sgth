<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\EstadoItemOrdenExamen;
use App\Enums\EstadoOrdenExamen;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\OrdenExamen;
use Illuminate\Support\Facades\DB;

/**
 * Emitir y anular órdenes de exámenes. El molde es la receta: folio del año
 * con bloqueo, anulación con motivo y sin borrado.
 */
final class OrdenExamenService implements OrdenExamenServiceInterface
{
    public function emitir(array $datos, array $items, int $solicitadoPor): OrdenExamen
    {
        return DB::transaction(function () use ($datos, $items, $solicitadoPor) {
            $orden = OrdenExamen::create([
                ...$datos,
                'folio'          => $this->generarFolio(),
                'solicitado_por' => $solicitadoPor,
                'estado'         => EstadoOrdenExamen::EMITIDA,
            ]);

            foreach ($items as $item) {
                $nota = trim((string) ($item['nota'] ?? ''));

                $orden->items()->create([
                    'catalogo_examen_id' => $item['catalogo_examen_id'],
                    'nota'               => $nota !== '' ? $nota : null,
                    'estado'             => EstadoItemOrdenExamen::PENDIENTE,
                ]);
            }

            return $orden;
        });
    }

    /**
     * Anula una orden que todavía espera resultados.
     *
     * Una completa no se anula: ya volvió todo lo que se pidió. Los resultados
     * que tenga una parcial se quedan en el historial (regla 3 del diseño).
     */
    public function anular(int $ordenId, string $motivo, int $anuladoPor): OrdenExamen
    {
        return DB::transaction(function () use ($ordenId, $motivo, $anuladoPor) {
            $orden = OrdenExamen::lockForUpdate()->findOrFail($ordenId);

            if ($orden->estado === EstadoOrdenExamen::ANULADA) {
                throw new ReglaNegocioException("La orden {$orden->folio} ya fue anulada.");
            }

            if (! $orden->estado->abierta()) {
                throw new ReglaNegocioException(
                    "La orden {$orden->folio} ya tiene todos sus resultados: no se anula."
                );
            }

            $orden->update([
                'estado'           => EstadoOrdenExamen::ANULADA,
                'anulado_en'       => now(),
                'anulado_por'      => $anuladoPor,
                'motivo_anulacion' => $motivo,
            ]);

            return $orden;
        });
    }

    /**
     * `EXA-2026-00001`: el número que va impreso y con el que el paciente
     * vuelve. El bloqueo de aviso serializa leer el máximo y escribir el folio
     * entre dos emisiones simultáneas, como en la receta.
     */
    private function generarFolio(): string
    {
        $anio = date('Y');

        DB::select('SELECT pg_advisory_xact_lock(?)', [
            crc32("orden_examen_folio_{$anio}"),
        ]);

        $ultimo = OrdenExamen::where('folio', 'like', "EXA-{$anio}-%")->max('folio');

        $secuencial = $ultimo
            ? (int) substr($ultimo, strlen("EXA-{$anio}-")) + 1
            : 1;

        return "EXA-{$anio}-" . str_pad((string) $secuencial, 5, '0', STR_PAD_LEFT);
    }
}

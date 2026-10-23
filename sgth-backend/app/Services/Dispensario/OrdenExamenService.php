<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Enums\EstadoItemOrdenExamen;
use App\Enums\EstadoOrdenExamen;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\ItemOrdenExamen;
use App\Models\Dispensario\OrdenExamen;
use App\Models\Dispensario\ResultadoMedico;
use Illuminate\Support\Facades\DB;

/**
 * El ciclo de una orden de exámenes: emitirla, anularla y colgarle lo que
 * vuelve del laboratorio. El molde es la receta: folio del año con bloqueo,
 * anulación con motivo y sin borrado.
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
     * Cuelga el resultado de los exámenes que cubre: cada uno pasa a recibido
     * y la orden recalcula su estado.
     *
     * Con la orden bloqueada (la lección de la #506): dos resultados subidos a
     * la vez no se pisan el recálculo. El request ya comprobó lo mismo, pero
     * entre su lectura y esta escritura pudo llegar otro.
     */
    public function adjuntarResultado(ResultadoMedico $resultado, array $itemIds): void
    {
        DB::transaction(function () use ($resultado, $itemIds) {
            $orden = OrdenExamen::lockForUpdate()->findOrFail($resultado->orden_examen_id);
            $this->exigirAbierta($orden, 'el resultado se sube sin orden o contra una orden nueva');

            $itemIds = array_values(array_unique($itemIds));
            $items = $orden->items()->with('examen:id,nombre')->whereKey($itemIds)->get();

            if ($items->count() !== count($itemIds)) {
                throw new ReglaNegocioException("Hay exámenes que no son de la orden {$orden->folio}.");
            }

            $recibidos = $items->where('estado', EstadoItemOrdenExamen::RECIBIDO);
            if ($recibidos->isNotEmpty()) {
                throw new ReglaNegocioException(
                    "Ya tienen resultado en la orden {$orden->folio}: "
                    . $recibidos->map(fn (ItemOrdenExamen $i) => $i->examen?->nombre)->implode(', ')
                    . '. Si se subió el archivo equivocado, retírelo antes.'
                );
            }

            // Uno marcado como no realizado también se cubre: el paciente se lo
            // hizo después de todo.
            $resultado->itemsOrden()->attach($itemIds);
            ItemOrdenExamen::whereKey($itemIds)->update([
                'estado'              => EstadoItemOrdenExamen::RECIBIDO,
                'motivo_no_realizado' => null,
            ]);

            $this->recalcularEstado($orden);
        });
    }

    /** El paciente no se lo hizo o el laboratorio no lo hace: deja de esperarse. */
    public function marcarNoRealizado(int $ordenId, int $itemId, string $motivo): ItemOrdenExamen
    {
        return DB::transaction(function () use ($ordenId, $itemId, $motivo) {
            $orden = OrdenExamen::lockForUpdate()->findOrFail($ordenId);
            $this->exigirAbierta($orden, 'ya no espera resultados');

            $item = $orden->items()->with('examen:id,nombre')->findOrFail($itemId);

            if ($item->estado !== EstadoItemOrdenExamen::PENDIENTE) {
                throw new ReglaNegocioException(
                    "{$item->examen?->nombre} ya no está pendiente en la orden {$orden->folio}."
                );
            }

            $item->update([
                'estado'              => EstadoItemOrdenExamen::NO_REALIZADO,
                'motivo_no_realizado' => $motivo,
            ]);

            $this->recalcularEstado($orden);

            return $item;
        });
    }

    /**
     * Al retirar un resultado, los exámenes que cubría y que ningún otro
     * cubre vuelven a pendiente, y la orden se recalcula: una completa puede
     * reabrirse (regla 4 del diseño). Se llama después de retirarlo, para que
     * ya no cuente entre los que cubren.
     */
    public function liberarResultado(ResultadoMedico $resultado): void
    {
        if ($resultado->orden_examen_id === null) {
            return;
        }

        DB::transaction(function () use ($resultado) {
            $orden = OrdenExamen::lockForUpdate()->findOrFail($resultado->orden_examen_id);

            $resultado->itemsOrden()
                ->where('orden_examen_items.estado', EstadoItemOrdenExamen::RECIBIDO)
                ->get()
                ->reject(fn (ItemOrdenExamen $item) => $item->resultados()->exists())
                ->each(fn (ItemOrdenExamen $item) => $item->update(['estado' => EstadoItemOrdenExamen::PENDIENTE]));

            $this->recalcularEstado($orden);
        });
    }

    /**
     * El estado de la orden sale de sus exámenes: ninguno resuelto, emitida;
     * todos recibidos o no realizados, completa; si no, parcial. Una anulada
     * se queda anulada aunque le lleguen o le quiten resultados.
     */
    private function recalcularEstado(OrdenExamen $orden): void
    {
        if ($orden->estado === EstadoOrdenExamen::ANULADA) {
            return;
        }

        $estados = $orden->items()->pluck('estado');
        $pendientes = $estados->filter(fn ($e) => $e === EstadoItemOrdenExamen::PENDIENTE)->count();

        $orden->update(['estado' => match (true) {
            $pendientes === $estados->count() => EstadoOrdenExamen::EMITIDA,
            $pendientes === 0                 => EstadoOrdenExamen::COMPLETA,
            default                           => EstadoOrdenExamen::PARCIAL,
        }]);
    }

    private function exigirAbierta(OrdenExamen $orden, string $consecuencia): void
    {
        if (! $orden->estado->abierta()) {
            throw new ReglaNegocioException(
                "La orden {$orden->folio} está {$orden->estado->value}: {$consecuencia}."
            );
        }
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

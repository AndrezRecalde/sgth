<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\PrestamoEstadoServiceInterface;
use App\Contracts\InventarioTi\PrestamoServiceInterface;
use App\Enums\EstadoPrestamo;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\PrestamoEquipo;
use App\Models\InventarioTi\PrestamoItem;
use App\Services\InventarioTi\Prestamos\EquiposDelPrestamo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El trámite de un préstamo (docs/diseno-subsistema-tic.md, 4.6):
 *
 * - **Aprobar** ocupa el horario de los equipos; la restricción de exclusión
 *   impide que dos préstamos queden aprobados para el mismo equipo y hora.
 * - **Entregar** y **recibir** anotan la fecha y la hora reales, quién por TIC
 *   y en qué condición sale y vuelve cada equipo. La condición con que vuelve
 *   pasa a la ficha del equipo.
 * - **Rechazar** es de TIC y **cancelar**, de TIC o del servidor, mientras no
 *   se haya entregado.
 *
 * Cada cambio queda en `historial_estados_prestamo`.
 */
final class PrestamoEstadoService implements PrestamoEstadoServiceInterface
{
    public function __construct(
        private readonly PrestamoServiceInterface $prestamos,
        private readonly EquiposDelPrestamo $equipos,
    ) {}

    public function aprobar(PrestamoEquipo $prestamo): PrestamoEquipo
    {
        return $this->cambiar($prestamo, [EstadoPrestamo::SOLICITADO], function (PrestamoEquipo $p) {
            if ($p->fin_previsto->isPast()) {
                throw new ReglaNegocioException('El horario pedido ya pasó: rechácelo o pida al servidor que solicite otro.');
            }

            $ids = $p->items->pluck('bien_informatico_id')->all();
            $this->equipos->prestables($ids);
            $this->equipos->exigirLibres($ids, $p->inicio_previsto, $p->fin_previsto, $p->id);

            $p->update(['estado' => EstadoPrestamo::RESERVADO, 'aprobado_por' => auth()->id(), 'aprobado_en' => now()]);
            $this->equipos->sincronizarBloqueo($p);
        });
    }

    public function rechazar(PrestamoEquipo $prestamo, string $motivo): PrestamoEquipo
    {
        return $this->cambiar($prestamo, [EstadoPrestamo::SOLICITADO], fn (PrestamoEquipo $p) => $p->update([
            'estado'         => EstadoPrestamo::RECHAZADO,
            'rechazado_por'  => auth()->id(),
            'rechazado_en'   => now(),
            'motivo_rechazo' => $motivo,
        ]), $motivo);
    }

    public function cancelar(PrestamoEquipo $prestamo, string $motivo): PrestamoEquipo
    {
        return $this->cambiar($prestamo, [EstadoPrestamo::SOLICITADO, EstadoPrestamo::RESERVADO], function (PrestamoEquipo $p) use ($motivo) {
            $p->update([
                'estado'             => EstadoPrestamo::CANCELADO,
                'cancelado_por'      => auth()->id(),
                'cancelado_en'       => now(),
                'motivo_cancelacion' => $motivo,
            ]);
            $this->equipos->sincronizarBloqueo($p);
        }, $motivo);
    }

    public function entregar(PrestamoEquipo $prestamo, array $datos): PrestamoEquipo
    {
        return $this->cambiar($prestamo, [EstadoPrestamo::RESERVADO], function (PrestamoEquipo $p) use ($datos) {
            $momento = $this->momento($datos['entregado_en'], 'entregado_en', $p->aprobado_en, 'la aprobación');
            $ids = $p->items->pluck('bien_informatico_id')->all();
            $this->equipos->prestables($ids);
            $this->equipos->exigirEnTic($ids, $p->id);

            $this->porItem($p, $datos['items'], ['accesorios', 'condicion_entrega']);
            $p->update([
                'estado'        => EstadoPrestamo::EN_CURSO,
                'entregado_por' => auth()->id(),
                'entregado_en'  => $momento,
                'observaciones' => $datos['observaciones'] ?? $p->observaciones,
            ]);
            $this->equipos->sincronizarBloqueo($p);
        });
    }

    public function recibir(PrestamoEquipo $prestamo, array $datos): PrestamoEquipo
    {
        return $this->cambiar($prestamo, [EstadoPrestamo::EN_CURSO], function (PrestamoEquipo $p) use ($datos) {
            $momento = $this->momento($datos['devuelto_en'], 'devuelto_en', $p->entregado_en, 'la entrega');

            $this->porItem($p, $datos['items'], ['condicion_devolucion', 'novedad']);
            // La condición con que vuelve es la que tiene el equipo ahora.
            foreach ($p->items()->get() as $item) {
                BienInformatico::whereKey($item->bien_informatico_id)->update(['condicion_fisica' => $item->condicion_devolucion]);
            }

            $p->update([
                'estado'        => EstadoPrestamo::DEVUELTO,
                'recibido_por'  => auth()->id(),
                'devuelto_en'   => $momento,
                'observaciones' => $datos['observaciones'] ?? $p->observaciones,
            ]);
            $this->equipos->sincronizarBloqueo($p);
        });
    }

    /**
     * Bloquea el préstamo, exige uno de los estados de partida, aplica el
     * cambio y lo anota en el historial.
     *
     * @param  list<EstadoPrestamo>  $desde
     */
    private function cambiar(PrestamoEquipo $prestamo, array $desde, callable $cambio, ?string $detalle = null): PrestamoEquipo
    {
        DB::transaction(function () use ($prestamo, $desde, $cambio, $detalle) {
            $p = PrestamoEquipo::with('items')->lockForUpdate()->findOrFail($prestamo->id);
            $anterior = $p->estado;

            if (! in_array($anterior, $desde, true)) {
                throw new ReglaNegocioException("El préstamo {$p->folio} está " . mb_strtolower($anterior->etiqueta()) . ': ya no admite ese paso.');
            }

            $cambio($p);
            $this->equipos->anotar($p, $anterior, $detalle);
        });

        return $this->prestamos->obtener($prestamo->id);
    }

    /** La fecha y hora real de la entrega o la devolución: no futura ni anterior al paso previo. */
    private function momento(string $valor, string $campo, ?Carbon $previo, string $nombrePrevio): Carbon
    {
        $momento = Carbon::parse($valor)->startOfMinute();

        if ($momento->gt(now()->addMinute())) {
            throw ValidationException::withMessages([$campo => 'No puede ser una hora futura.']);
        }
        if ($previo && $momento->lt($previo->copy()->startOfMinute())) {
            throw ValidationException::withMessages([$campo => "No puede ser anterior a {$nombrePrevio} ({$previo->format('d/m/Y H:i')})."]);
        }

        return $momento;
    }

    /**
     * Los datos de cada equipo del préstamo. Tienen que venir todos, y solo
     * ellos.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $campos
     */
    private function porItem(PrestamoEquipo $prestamo, array $items, array $campos): void
    {
        $porId = collect($items)->keyBy(fn ($i) => (int) $i['id']);
        $propios = $prestamo->items->pluck('id');

        if ($porId->keys()->sort()->values()->all() !== $propios->sort()->values()->all()) {
            throw ValidationException::withMessages(['items' => 'Indique la condición de cada equipo del préstamo, y solo de ellos.']);
        }

        foreach ($prestamo->items as $item) {
            PrestamoItem::whereKey($item->id)->update(collect($porId[$item->id])->only($campos)->all());
        }
    }
}

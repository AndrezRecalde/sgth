<?php

namespace App\Services\InventarioTi\Prestamos;

use App\Contracts\InventarioTi\ReglasPrestamoServiceInterface;
use App\Enums\EstadoPrestamo;
use App\Models\InventarioTi\PrestamoEquipo;
use Illuminate\Support\Facades\DB;

/**
 * Lo que pasa solo con el reloj (docs/diseno-subsistema-tic.md, 4.6 y 5.3;
 * fase 2.4). Lo corre `sgth:prestamos:seguimiento` cada quince minutos.
 *
 * - **No retirado:** la reserva cuyo inicio pasó hace más que el margen de la
 *   regla, sin entregarse, se cierra y libera el horario.
 * - **Recordatorio:** una hora antes de la devolución prevista, una vez.
 * - **Vencido:** al servidor y a TIC, una vez, cuando pasa la hora de
 *   devolución sin recibirse.
 */
final class SeguimientoPrestamoService
{
    /** Cuánto antes de la devolución sale el recordatorio. */
    public const ANTICIPO_RECORDATORIO_MINUTOS = 60;

    public function __construct(
        private readonly ReglasPrestamoServiceInterface $reglas,
        private readonly EquiposDelPrestamo $equipos,
        private readonly AvisoPrestamoService $avisos,
    ) {}

    /** @return array{no_retirados: int, recordatorios: int, vencidos: int} */
    public function correr(): array
    {
        return [
            'no_retirados'  => $this->cerrarNoRetirados(),
            'recordatorios' => $this->recordar(),
            'vencidos'      => $this->avisarVencidos(),
        ];
    }

    public function cerrarNoRetirados(): int
    {
        $margen = $this->reglas->margenRetiro();
        $ids = PrestamoEquipo::where('estado', EstadoPrestamo::RESERVADO)
            ->where('inicio_previsto', '<', now()->subMinutes($margen))
            ->pluck('id');

        $cerrados = 0;
        foreach ($ids as $id) {
            $prestamo = DB::transaction(function () use ($id, $margen) {
                $p = PrestamoEquipo::lockForUpdate()->find($id);
                // Pudo entregarse o cancelarse entre la consulta y el bloqueo.
                if (! $p || $p->estado !== EstadoPrestamo::RESERVADO) {
                    return null;
                }

                $p->update(['estado' => EstadoPrestamo::NO_RETIRADO]);
                $this->equipos->sincronizarBloqueo($p);
                $this->equipos->anotar($p, EstadoPrestamo::RESERVADO, "No se retiró dentro de los {$margen} minutos de margen: el horario se liberó.");

                return $p;
            });

            if ($prestamo) {
                $this->avisos->alServidor($prestamo, 'no_retirado');
                $cerrados++;
            }
        }

        return $cerrados;
    }

    public function recordar(): int
    {
        $prestamos = PrestamoEquipo::where('estado', EstadoPrestamo::EN_CURSO)
            ->whereNull('recordatorio_enviado_en')
            ->where('fin_previsto', '>', now())
            ->where('fin_previsto', '<=', now()->addMinutes(self::ANTICIPO_RECORDATORIO_MINUTOS))
            ->get();

        foreach ($prestamos as $p) {
            // Se anota aunque el correo no salga: un servidor sin dirección no
            // se reintenta cada quince minutos.
            $p->update(['recordatorio_enviado_en' => now()]);
            $this->avisos->alServidor($p, 'recordatorio');
        }

        return $prestamos->count();
    }

    public function avisarVencidos(): int
    {
        $prestamos = PrestamoEquipo::vencidos()->whereNull('aviso_vencido_en')->get();

        foreach ($prestamos as $p) {
            $p->update(['aviso_vencido_en' => now()]);
            $this->avisos->alServidor($p, 'vencido');
            $this->avisos->aTic($p);
        }

        return $prestamos->count();
    }
}

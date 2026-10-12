<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\LoteAccion;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Los lotes que se tramitan enteros: los actos sobre un puesto, uno por
 * ocupante —el traspaso (fase 7.1a) y la revisión de la clasificación
 * (7.1b)—. Un puesto no puede quedar con unos ocupantes en cada situación, así
 * que se registran todos o ninguno, y se anulan todos juntos. El incremento de
 * los obreros no: cada uno es independiente (fase 4.6).
 */
class LoteEnteroService
{
    public function __construct(private readonly MovimientoPersonalStateService $estados)
    {
    }

    public static function esEntero(LoteAccion $lote): bool
    {
        return in_array($lote->tipo, [LoteAccion::TRASPASO_PUESTO, LoteAccion::REVISION_CLASIFICACION], true);
    }

    /**
     * Suscribe y registra el lote entero, en una transacción: si uno no
     * puede, no se registra ninguno, y se dice quién y por qué.
     *
     * @return array{registrados: int, fallidos: list<array{movimiento_id: int, nombre: string, mensaje: string}>}
     */
    public function registrar(LoteAccion $lote): array
    {
        $this->exigirEntero($lote);

        // El dictamen es uno para todos, cuando el lote sube la remuneración.
        $alSuscribir = ['por_el_lote' => true, ...array_filter(
            ['dictamen_presupuestario_ref' => $lote->dictamen_presupuestario_ref],
        )];
        $registrados = 0;

        DB::transaction(function () use ($lote, $alSuscribir, &$registrados) {
            foreach ($this->enTramite($lote) as $movimiento) {
                try {
                    if ($movimiento->estado === EstadoAccionPersonal::BORRADOR) {
                        $movimiento = $this->estados->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA, $alSuscribir);
                    }

                    $this->estados->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA, ['por_el_lote' => true]);
                    $registrados++;
                } catch (ReglaNegocioException $e) {
                    throw new ReglaNegocioException(
                        "No se registró el lote: con {$this->nombre($movimiento->servidor)}, {$e->getMessage()} "
                            .'Ningún acto del lote quedó registrado.'
                    );
                }
            }
        });

        return ['registrados' => $registrados, 'fallidos' => []];
    }

    /** Anula lo que sigue en trámite, si nada se registró todavía. */
    public function descartar(LoteAccion $lote, string $motivo): int
    {
        $this->exigirEntero($lote);

        if ($this->registrados($lote)->isNotEmpty()) {
            throw new ReglaNegocioException(
                'El lote ya está registrado: para dejarlo sin efecto, reviértalo desde el lote.'
            );
        }

        return $this->anular($this->enTramite($lote), "Se descartó el lote #{$lote->id}: {$motivo}");
    }

    /**
     * Deja sin efecto el lote registrado: anula todos sus actos, y el puesto y
     * sus ocupantes vuelven a como estaban.
     */
    public function revertir(LoteAccion $lote, string $motivo): int
    {
        $this->exigirEntero($lote);

        $actos = $this->registrados($lote)->concat($this->enTramite($lote));

        if ($actos->isEmpty()) {
            throw new ReglaNegocioException('El lote no tiene actos que revertir.');
        }

        return $this->anular($actos, "Se revirtió el lote #{$lote->id}: {$motivo}");
    }

    /** @param  Collection<int, MovimientoPersonal>  $actos */
    private function anular(Collection $actos, string $motivo): int
    {
        $anulados = 0;

        DB::transaction(function () use ($actos, $motivo, &$anulados) {
            // De la última hacia atrás, como se anula siempre.
            foreach ($actos->sortByDesc('id') as $movimiento) {
                $this->estados->transicionar($movimiento->fresh(), EstadoAccionPersonal::ANULADA, [
                    'motivo_anulacion' => $motivo,
                    'por_el_lote'      => true,
                ]);
                $anulados++;
            }
        });

        return $anulados;
    }

    private function exigirEntero(LoteAccion $lote): void
    {
        if (! self::esEntero($lote)) {
            throw new \LogicException("El lote #{$lote->id} no se tramita entero.");
        }
    }

    /** @return Collection<int, MovimientoPersonal> */
    private function enTramite(LoteAccion $lote): Collection
    {
        return $lote->movimientos()
            ->whereIn('estado', [EstadoAccionPersonal::BORRADOR->value, EstadoAccionPersonal::SUSCRITA->value])
            ->with('servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, MovimientoPersonal> */
    private function registrados(LoteAccion $lote): Collection
    {
        return $lote->movimientos()
            ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
            ->orderBy('id')
            ->get();
    }

    private function nombre(?Servidor $s): string
    {
        return trim(implode(' ', array_filter([$s?->apellido, $s?->segundo_apellido, $s?->nombre, $s?->segundo_nombre])))
            ?: '—';
    }
}

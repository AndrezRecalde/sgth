<?php

namespace App\Services\Disciplinario;

use App\Enums\EstadoSumario;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Expediente\MovimientoPersonal;
use Illuminate\Support\Str;

/**
 * Anular ↔ sanción (diseño de Acciones de Personal, 9.6; fase 2.5).
 *
 * Anular la acción de una sanción —o la cesación de una destitución— deja la
 * sanción sin efecto. Hasta aquí el sumario seguía «Resuelto» y su detalle
 * decía «Sanción impuesta» con la acción anulada al lado, y no había forma de
 * dictar otra: el sumario admitía una sola sanción para siempre.
 *
 * La sanción no se borra: queda en el sumario, con cuándo, por qué y quién. Si
 * el sumario estaba resuelto vuelve a «Con informe», para que la autoridad
 * dicte la correcta —anular y emitir de nuevo, que es la regla de TH—. Apelado
 * o cerrado no se toca: ahí lo que sigue es el cierre.
 *
 * Se anula desde un solo sitio, la acción. Disciplinario no tiene un botón
 * propio, para que los dos registros no lleguen a decir cosas distintas.
 */
final class SancionSinEfectoService
{
    public function porAnulacionDe(MovimientoPersonal $accion): void
    {
        $sancion = SancionDisciplinaria::where('movimiento_personal_id', $accion->id)
            ->whereNull('anulada_en')
            ->first();

        if (! $sancion) {
            return;
        }

        $codigo = $accion->codigo_registro ? " {$accion->codigo_registro}" : '';
        $motivo = trim((string) $accion->motivo_anulacion);

        $sancion->update([
            'anulada_en'       => now(),
            'motivo_anulacion' => Str::limit(
                "Se anuló su acción de personal{$codigo}".($motivo !== '' ? ": {$motivo}" : '.'),
                500
            ),
            'anulada_por'      => auth()->id(),
            'sin_efecto_por_movimiento_id' => $accion->id,
            'updated_by'       => auth()->id(),
        ]);

        $sumario = $sancion->sumario;

        if ($sumario?->estado === EstadoSumario::RESUELTO) {
            $sumario->update([
                'estado'           => EstadoSumario::CON_INFORME,
                'fecha_resolucion' => null,
                'updated_by'       => auth()->id(),
            ]);
        }
    }

    /**
     * La restitución deja sin efecto la destitución por sentencia (fase 2.6),
     * y con ella la sanción. El sumario no se mueve: lo decidió un juez, y no
     * hay otra sanción que dictar.
     */
    public function porSentencia(MovimientoPersonal $restitucion): void
    {
        if (! $restitucion->movimiento_relacionado_id) {
            return;
        }

        $sancion = SancionDisciplinaria::where('movimiento_personal_id', $restitucion->movimiento_relacionado_id)
            ->whereNull('anulada_en')
            ->first();

        if (! $sancion) {
            return;
        }

        $sancion->update([
            'anulada_en'       => now(),
            'motivo_anulacion' => Str::limit(
                "Dejada sin efecto por sentencia: causa {$restitucion->causa_judicial_numero}, "
                    ."{$restitucion->unidad_judicial}, ejecutoriada el "
                    .$restitucion->fecha_ejecutoria?->format('d/m/Y')
                    .($restitucion->codigo_registro ? " (restitución {$restitucion->codigo_registro})." : '.'),
                500
            ),
            'anulada_por'      => auth()->id(),
            'sin_efecto_por_movimiento_id' => $restitucion->id,
            'updated_by'       => auth()->id(),
        ]);
    }

    /** Anular la restitución devuelve la sanción a su sitio. */
    public function revertirSentencia(MovimientoPersonal $restitucion): void
    {
        SancionDisciplinaria::where('sin_efecto_por_movimiento_id', $restitucion->id)
            ->get()
            ->each(fn (SancionDisciplinaria $s) => $s->update([
                'anulada_en'       => null,
                'motivo_anulacion' => null,
                'anulada_por'      => null,
                'sin_efecto_por_movimiento_id' => null,
                'updated_by'       => auth()->id(),
            ]));
    }
}

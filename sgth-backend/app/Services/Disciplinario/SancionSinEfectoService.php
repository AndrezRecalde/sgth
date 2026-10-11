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
}

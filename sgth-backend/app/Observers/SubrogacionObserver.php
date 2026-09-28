<?php

namespace App\Observers;

use App\Enums\EstadoSubrogacion;
use App\Models\Expediente\Subrogacion;

/**
 * Bitácora de una subrogación en activity_log.
 *
 * Los cambios de estado se registran aparte y con el estado anterior dentro,
 * porque son los que importan: activarse es el momento en que alguien adquiere
 * la facultad de firmar, y cancelarse el momento en que la pierde. Un
 * «actualizado» a secas no permitía reconstruir ninguno de los dos.
 */
class SubrogacionObserver
{
    public function created(Subrogacion $subrogacion): void
    {
        activity()
            ->performedOn($subrogacion)
            ->withProperties([
                'tipo'                => $subrogacion->tipo?->value,
                'estado'              => $subrogacion->estado?->value,
                'puesto_subrogado_id' => $subrogacion->puesto_subrogado_id,
            ])
            ->event('created')
            ->log('creado');
    }

    public function updated(Subrogacion $subrogacion): void
    {
        if ($subrogacion->wasChanged('estado')) {
            activity()
                ->performedOn($subrogacion)
                ->withProperties([
                    'estado_anterior' => $this->comoTexto($subrogacion->getOriginal('estado')),
                    'estado'          => $subrogacion->estado?->value,
                ])
                ->event('updated')
                ->log('cambió de estado');

            return;
        }

        activity()->performedOn($subrogacion)->event('updated')->log('actualizado');
    }

    /** `getOriginal()` aplica los casts, así que puede devolver el enum. */
    private function comoTexto(mixed $estado): ?string
    {
        return $estado instanceof EstadoSubrogacion
            ? $estado->value
            : ($estado === null ? null : (string) $estado);
    }
}

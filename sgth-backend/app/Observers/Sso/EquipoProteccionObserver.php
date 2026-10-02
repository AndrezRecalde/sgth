<?php

namespace App\Observers\Sso;

use App\Models\Sso\EquipoProteccion;

/**
 * Rastro del catálogo de equipos de protección en `activity_log`.
 *
 * El catálogo decide qué se entrega y cada cuánto se repone —la vida útil es
 * uno de los dos plazos con los que `EstadoKitEpp` resuelve si un equipo toca—,
 * así que cambiarla cambia lo que el sistema pide entregar.
 */
class EquipoProteccionObserver
{
    use RegistraActividadSso;

    public function created(EquipoProteccion $equipo): void
    {
        $this->registrar($equipo, 'created', 'Equipo de protección registrado', $this->datos($equipo));
    }

    public function updated(EquipoProteccion $equipo): void
    {
        $this->registrar($equipo, 'updated', 'Equipo de protección actualizado', $this->datos($equipo));
    }

    public function deleted(EquipoProteccion $equipo): void
    {
        $this->registrar($equipo, 'deleted', 'Equipo de protección eliminado', $this->datos($equipo));
    }

    public function restored(EquipoProteccion $equipo): void
    {
        $this->registrar($equipo, 'restored', 'Equipo de protección restaurado', $this->datos($equipo));
    }

    /** @return array<string, mixed> */
    private function datos(EquipoProteccion $equipo): array
    {
        return [
            'codigo'          => $equipo->codigo,
            'tipo'            => $equipo->tipo,
            'norma_tecnica'   => $equipo->norma_tecnica,
            'vida_util_meses' => $equipo->vida_util_meses,
            'estado'          => $equipo->estado,
        ];
    }
}

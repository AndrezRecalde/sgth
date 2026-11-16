<?php

namespace App\Services\Estructura;

use App\Enums\TipoSubrogacion;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Subrogacion;
use Illuminate\Support\Collection;

/**
 * Qué unidades dirige un servidor en una fecha.
 *
 * Es la regla de `FirmanteOrganigramaService` vista desde el otro lado: dirige
 * una unidad quien tiene contrato vigente en su puesto de jefatura, o quien lo
 * subroga o lo tiene encargado ese día. No la asigna nadie: cambia sola cuando
 * cambia el puesto. Reemplaza al rol `jefe-unidad`, que TI daba a mano y nada
 * retiraba cuando la persona dejaba la jefatura (decisión del 2026-11-13).
 *
 * El titular sigue dirigiendo mientras alguien lo subroga: la subrogación
 * cubre su ausencia, no le quita la unidad. Solo cuenta la unidad del puesto,
 * sin las que dependen de ella.
 */
class JefaturaService
{
    /**
     * @return Collection<int, array{unidad_id: int, unidad: ?string, como: string}>
     */
    public function jefaturasDe(?int $servidorId, ?string $fecha = null): Collection
    {
        if (! $servidorId) {
            return collect();
        }

        $fecha ??= now()->toDateString();

        // `vigente`, como `FirmanteOrganigramaService::titularDe()`: es lo que
        // decide quién firma por la unidad.
        $comoTitular = ContratoServidor::query()
            ->where('servidor_id', $servidorId)
            ->where('estado', 'vigente')
            ->whereHas('puesto', fn ($q) => $q->where('es_jefe', true))
            ->with('puesto.unidadAdministrativa:id,nombre')
            ->get()
            ->map(fn (ContratoServidor $contrato) => [
                'unidad_id' => (int) $contrato->puesto->unidad_administrativa_id,
                'unidad'    => $contrato->puesto->unidadAdministrativa?->nombre,
                'como'      => 'titular',
            ]);

        // Una subrogación de un puesto que no es jefatura no da equipo.
        $porSubrogacion = Subrogacion::query()
            ->where('servidor_subrogante_id', $servidorId)
            ->activaEnFecha($fecha)
            ->whereHas('puestoSubrogado', fn ($q) => $q->where('es_jefe', true))
            ->with('puestoSubrogado.unidadAdministrativa:id,nombre')
            ->get()
            ->map(fn (Subrogacion $subrogacion) => [
                'unidad_id' => (int) $subrogacion->puestoSubrogado->unidad_administrativa_id,
                'unidad'    => $subrogacion->puestoSubrogado->unidadAdministrativa?->nombre,
                'como'      => $subrogacion->tipo === TipoSubrogacion::ENCARGO ? 'encargo' : 'subrogacion',
            ]);

        return $comoTitular->concat($porSubrogacion)->unique('unidad_id')->values();
    }

    /** @return list<int> */
    public function unidadesQueDirige(?int $servidorId, ?string $fecha = null): array
    {
        return $this->jefaturasDe($servidorId, $fecha)->pluck('unidad_id')->all();
    }
}

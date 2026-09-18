<?php

namespace App\Services\Estructura;

use App\Enums\EstadoSubrogacion;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;

/**
 * Quién firma por una unidad en una fecha, según el organigrama.
 *
 * Es el jefe titular del puesto de jefatura, o quien lo subrogue si hay una
 * subrogación activa ese día: quien ejerce el cargo es quien firma.
 *
 * La lógica nació en `FirmanteAccionPersonalService`, que solo sabía de dos
 * unidades ancladas —Talento Humano y la máxima autoridad—. Viáticos necesita
 * además la unidad financiera y la del servidor que viaja, así que vive aquí y
 * los dos módulos la comparten.
 */
class FirmanteOrganigramaService
{
    /** La unidad marcada con esa bandera, si alguien la marcó. */
    public function unidadAnclada(string $bandera): ?UnidadAdministrativa
    {
        return UnidadAdministrativa::where($bandera, true)->first();
    }

    /**
     * @param  UnidadAdministrativa|int|null  $unidad
     * @return array{servidor: ?Servidor, cargo: ?string, subrogado: bool, puesto_id: ?int}
     */
    public function jefeDeUnidadEn(mixed $unidad, string $fecha, ?string $cargoPorDefecto = null): array
    {
        $unidadId = $unidad instanceof UnidadAdministrativa ? $unidad->id : $unidad;

        $puesto = $unidadId
            ? \App\Models\Estructura\Puesto::where('unidad_administrativa_id', $unidadId)
                ->where('es_jefe', true)
                ->with('cargo')
                ->first()
            : null;

        if (! $puesto) {
            return ['servidor' => null, 'cargo' => $cargoPorDefecto, 'subrogado' => false, 'puesto_id' => null];
        }

        $cargo      = $puesto->cargo?->nombre ?? $cargoPorDefecto;
        $subrogante = $this->subroganteDe($puesto->id, $fecha);

        return [
            'servidor'  => $subrogante ?? $this->titularDe($puesto->id),
            'cargo'     => $cargo,
            'subrogado' => (bool) $subrogante,
            'puesto_id' => $puesto->id,
        ];
    }

    /**
     * Titular del puesto: el servidor con contrato vigente sobre él. Si el
     * puesto está vacante devuelve null y el documento sale con el cargo pero
     * sin nombre — es preferible a atribuirle la firma a alguien que no la dio.
     */
    public function titularDe(int $puestoId): ?Servidor
    {
        return Servidor::whereHas(
            'contratos',
            fn ($q) => $q->where('puesto_id', $puestoId)->where('estado', 'vigente')
        )->first();
    }

    public function subroganteDe(int $puestoId, string $fecha): ?Servidor
    {
        $subrogacion = Subrogacion::with('subrogante')
            ->where('puesto_subrogado_id', $puestoId)
            ->where('estado', EstadoSubrogacion::ACTIVA->value)
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fecha);
            })
            ->orderByDesc('fecha_inicio')
            ->first();

        return $subrogacion?->subrogante;
    }

    public function nombreCompleto(Servidor $servidor): string
    {
        return trim(implode(' ', array_filter([
            $servidor->apellido,
            $servidor->segundo_apellido,
            $servidor->nombre,
            $servidor->segundo_nombre,
        ])));
    }
}

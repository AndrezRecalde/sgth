<?php

namespace App\Services\Estructura;

use App\Enums\TipoSubrogacion;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
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
     * `encargo` dice si quien firma lo hace por encargo —el puesto no tiene
     * titular—, que en el papel se marca (E) y no (S).
     *
     * @param  UnidadAdministrativa|int|null  $unidad
     * @return array{servidor: ?Servidor, cargo: ?string, subrogado: bool, encargo: bool, puesto_id: ?int}
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
            return ['servidor' => null, 'cargo' => $cargoPorDefecto, 'subrogado' => false, 'encargo' => false, 'puesto_id' => null];
        }

        $cargo       = $puesto->cargo?->nombre ?? $cargoPorDefecto;
        $subrogacion = $this->subrogacionDe($puesto->id, $fecha);
        $subrogante  = $subrogacion?->subrogante;

        return [
            'servidor'  => $subrogante ?? $this->titularDe($puesto->id),
            'cargo'     => $cargo,
            'subrogado' => (bool) $subrogante,
            'encargo'   => $subrogante !== null && $subrogacion->tipo === TipoSubrogacion::ENCARGO,
            'puesto_id' => $puesto->id,
        ];
    }

    /**
     * Titular del puesto: el servidor con contrato vigente sobre él. Si el
     * puesto está vacante devuelve null y el documento sale con el cargo pero
     * sin nombre — es preferible a atribuirle la firma a alguien que no la dio.
     *
     * Dos a la vez solo los hay el día del relevo de una autoridad electa, que
     * no ocupa plaza: desde la posesión firma el que entra, el de inicio más
     * reciente (2026-10-21). Antes salía el primero que devolviera la base.
     */
    public function titularDe(int $puestoId): ?Servidor
    {
        return ContratoServidor::where('puesto_id', $puestoId)
            ->where('estado', 'vigente')
            ->whereHas('servidor')
            ->with('servidor')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first()
            ?->servidor;
    }

    public function subroganteDe(int $puestoId, string $fecha): ?Servidor
    {
        return $this->subrogacionDe($puestoId, $fecha)?->subrogante;
    }

    /** La subrogación o el encargo que surte efecto ese día sobre el puesto. */
    public function subrogacionDe(int $puestoId, string $fecha): ?Subrogacion
    {
        // El predicado «surte efecto ese día» vive en el scope del modelo, que
        // es el único sitio donde se escribe. Aquí estaba copiado con una rama
        // de más —`whereNull('fecha_fin')`— para una columna que es NOT NULL
        // desde la primera migración: no podía ser verdad nunca.
        return Subrogacion::with('subrogante')
            ->where('puesto_subrogado_id', $puestoId)
            ->activaEnFecha($fecha)
            ->orderByDesc('fecha_inicio')
            ->first();
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

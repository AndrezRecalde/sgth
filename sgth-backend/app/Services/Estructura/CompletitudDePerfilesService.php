<?php

namespace App\Services\Estructura;

use App\Enums\TipoPuesto;
use App\Models\Estructura\Puesto;

/**
 * El tablero de completitud de los perfiles (docs/diseno-manual-de-puestos.md,
 * P6, PR 15): qué le falta a cada puesto activo para quedar listo para
 * evaluar. Sin aprobación (TH, D6), «listo» es «completo»: misión, al menos una
 * alternativa de instrucción, al menos una actividad activa y al menos una
 * competencia.
 */
final class CompletitudDePerfilesService
{
    /** Lo que se mira, en el orden de la ficha. */
    public const PARTES = ['mision', 'requisitos', 'actividades', 'competencias'];

    /**
     * Los puestos, primero los incompletos y entre ellos los ocupados, que
     * son los que se evalúan; después por unidad y cargo.
     *
     * @return array{puestos: list<array<string, mixed>>, resumen: array<string, int>}
     */
    public function tablero(): array
    {
        $puestos = Puesto::query()
            ->where('puestos.activo', true)
            ->conOcupacion()
            ->with([
                'cargo:id,nombre',
                'unidadAdministrativa:id,nombre',
                'grupoOcupacional',
                'perfil' => fn ($q) => $q->select('id', 'puesto_id', 'mision')->withCount('requisitos'),
            ])
            ->withCount(['actividadesActivas', 'competencias'])
            ->get()
            ->map(fn (Puesto $p) => $this->fila($p))
            ->sortBy([
                ['completo', 'asc'],
                ['ocupado', 'desc'],
                ['unidad_administrativa.nombre', 'asc'],
                ['cargo.nombre', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $resumen = [
            'total'                => $puestos->count(),
            'completos'            => $puestos->where('completo', true)->count(),
            'incompletos'          => $puestos->where('completo', false)->count(),
            'ocupados_incompletos' => $puestos->where('completo', false)->where('ocupado', true)->count(),
        ];
        foreach (self::PARTES as $parte) {
            $resumen["sin_{$parte}"] = $puestos->filter(fn (array $p) => in_array($parte, $p['faltan'], true))->count();
        }

        return ['puestos' => $puestos->all(), 'resumen' => $resumen];
    }

    /** @return array<string, mixed> */
    private function fila(Puesto $puesto): array
    {
        $contenido = [
            'mision'       => filled($puesto->perfil?->mision),
            'requisitos'   => (int) ($puesto->perfil?->requisitos_count ?? 0),
            'actividades'  => (int) $puesto->actividades_activas_count,
            'competencias' => (int) $puesto->competencias_count,
        ];
        $faltan = array_values(array_filter(self::PARTES, fn (string $parte) => ! $contenido[$parte]));

        return [
            'id'    => $puesto->id,
            'tipo'  => ($puesto->tipo ?? TipoPuesto::PLANTILLA)->value,
            'cargo' => $puesto->cargo ? ['nombre' => $puesto->cargo->nombre] : null,
            'unidad_administrativa' => $puesto->unidadAdministrativa
                ? ['id' => $puesto->unidadAdministrativa->id, 'nombre' => $puesto->unidadAdministrativa->nombre]
                : null,
            // Lo que el cajón del perfil muestra y propone (D5).
            'grupo_ocupacional' => $puesto->grupoOcupacional ? [
                'grupo'                      => $puesto->grupoOcupacional->grupo,
                'grado_codigo'               => $puesto->grupoOcupacional->grado_codigo,
                'nivel_competencia_sugerido' => $puesto->grupoOcupacional->nivelDeCompetenciaSugerido(),
            ] : null,
            'regimen_laboral'   => $puesto->regimen_laboral,
            'nivel_complejidad' => $puesto->nivel_complejidad,
            'ocupado'           => (bool) $puesto->getAttribute('ocupado'),
            ...$contenido,
            'faltan'            => $faltan,
            'completo'          => $faltan === [],
        ];
    }
}

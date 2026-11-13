<?php

namespace App\Services\Seleccion;

use App\Enums\EstadoConvocatoria;
use App\Enums\NivelInstruccion;
use App\Enums\TipoCompetencia;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoActividad;
use App\Models\Estructura\PuestoCompetencia;
use App\Models\Estructura\PuestoRequisitoInstruccion;
use App\Models\Seleccion\Convocatoria;

/**
 * El perfil del puesto en la convocatoria (docs/diseno-manual-de-puestos.md,
 * P7, PR 16): la misión, los requisitos de instrucción y experiencia, la
 * capacitación, las competencias y las actividades salen del perfil, no de un
 * texto escrito a mano.
 *
 * Mientras la convocatoria es un borrador, muestra el perfil vigente del
 * puesto: si TH lo corrige, la convocatoria lo ve. Al publicarla, el perfil se
 * guarda en sus bases (`bases_concurso.perfil`), porque lo que se anunció no
 * cambia aunque después se edite el puesto.
 */
final class PerfilDeLaConvocatoria
{
    /** Lo que una convocatoria no debería anunciar sin tener. */
    public const PARTES = ['mision', 'requisitos', 'competencias'];

    /**
     * El perfil que muestra la convocatoria, con de dónde sale: el guardado al
     * publicarla o el vigente del puesto. `null` sin puesto, como los
     * contenedores de Reclutamiento Express.
     *
     * @return array<string, mixed>|null
     */
    public function de(Convocatoria $convocatoria): ?array
    {
        if (! self::muestraElVigente($convocatoria)) {
            return [...$convocatoria->bases_concurso['perfil'], 'fuente' => 'convocatoria'];
        }

        // En borrador, y en las que se publicaron antes de que se guardara el
        // perfil, el vigente; la pantalla advierte el segundo caso.
        $puesto = $convocatoria->puesto_id ? Puesto::find($convocatoria->puesto_id) : null;

        return $puesto ? [...$this->foto($puesto), 'tomado_en' => null, 'fuente' => 'puesto'] : null;
    }

    /**
     * Las bases con el perfil guardado, para publicar.
     *
     * @return array<string, mixed>
     */
    public function basesAlPublicar(Convocatoria $convocatoria): array
    {
        $bases  = $convocatoria->bases_concurso ?? [];
        $puesto = Puesto::find($convocatoria->puesto_id);

        return $puesto ? [...$bases, 'perfil' => $this->foto($puesto)] : $bases;
    }

    /**
     * Si la convocatoria muestra el perfil vigente del puesto y no el guardado:
     * en borrador siempre, aunque las bases traigan uno.
     */
    public static function muestraElVigente(Convocatoria $convocatoria): bool
    {
        return $convocatoria->estado === EstadoConvocatoria::BORRADOR
            || ! is_array($convocatoria->bases_concurso['perfil'] ?? null);
    }

    /**
     * El perfil del puesto tal como está hoy, en texto: lo que se guarda no
     * depende de catálogos que cambien después.
     *
     * @return array<string, mixed>
     */
    public function foto(Puesto $puesto): array
    {
        $puesto->load(['perfil.requisitos', 'actividadesActivas', 'competencias.competencia']);
        $perfil = $puesto->perfil;

        $requisitos = ($perfil?->requisitos ?? collect())
            ->map(fn (PuestoRequisitoInstruccion $r) => [
                'nivel_instruccion' => $r->nivel_instruccion instanceof NivelInstruccion ? $r->nivel_instruccion->value : $r->nivel_instruccion,
                'instruccion'       => $r->nivel_instruccion instanceof NivelInstruccion ? $r->nivel_instruccion->etiqueta() : (string) $r->nivel_instruccion,
                'detalle'           => $r->detalle,
                'meses_experiencia' => (int) $r->meses_experiencia,
            ])
            ->values()
            ->all();

        // Primero las técnicas, cada tipo en el orden del diccionario, como en
        // el perfil del puesto.
        $competencias = $puesto->competencias
            ->filter(fn (PuestoCompetencia $c) => $c->competencia !== null)
            ->sortBy([
                fn (PuestoCompetencia $a, PuestoCompetencia $b) => ($a->competencia->tipo === TipoCompetencia::TECNICA ? 0 : 1) <=> ($b->competencia->tipo === TipoCompetencia::TECNICA ? 0 : 1),
                fn (PuestoCompetencia $a, PuestoCompetencia $b) => $a->competencia->numero <=> $b->competencia->numero,
            ])
            ->map(fn (PuestoCompetencia $c) => [
                'nombre'         => $c->competencia->nombre,
                'tipo'           => $c->competencia->tipo->value,
                'nivel'          => $c->nivel->value,
                'comportamiento' => $c->competencia->getAttribute("comportamiento_{$c->nivel->value}"),
            ])
            ->values()
            ->all();

        $foto = [
            'tomado_en'                 => now()->toIso8601String(),
            'mision'                    => $perfil?->mision,
            'area_conocimiento'         => $perfil?->area_conocimiento,
            'especificidad_experiencia' => $perfil?->especificidad_experiencia,
            'capacitacion'              => $perfil?->capacitacion,
            'requisitos'                => $requisitos,
            'competencias'              => $competencias,
            'actividades'               => $puesto->actividadesActivas->map(fn (PuestoActividad $a) => $a->descripcion)->values()->all(),
        ];

        $foto['faltan'] = array_values(array_filter(self::PARTES, fn (string $parte) => blank($foto[$parte])));

        return $foto;
    }
}

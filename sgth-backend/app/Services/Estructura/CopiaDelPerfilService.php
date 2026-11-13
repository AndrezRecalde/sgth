<?php

namespace App\Services\Estructura;

use App\Enums\NivelCompetencia;
use App\Models\Estructura\FichaManualPuestos;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoActividad;
use App\Models\Estructura\PuestoCompetencia;
use App\Models\Estructura\PuestoPerfil;
use App\Models\Estructura\PuestoRequisitoInstruccion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Copiar el perfil de otro puesto (docs/diseno-manual-de-puestos.md, P6,
 * PR 15). Así se describen los puestos que no tienen ficha en el manual (TH,
 * D10): los de las unidades nuevas, los de obreros y los de contrato.
 *
 * Reemplaza, como la carga del manual: la misión, las relaciones, los
 * requisitos, la capacitación, las actividades y las competencias. El grado,
 * la unidad y el cargo siguen siendo los del puesto.
 *
 * - El subproceso no se copia: dice dónde está el puesto, no qué hace.
 * - Las actividades desactivadas y las competencias retiradas del diccionario
 *   no se copian.
 * - El nivel de las competencias sigue al grado (D5): las que el otro puesto
 *   exige en el nivel de su grado pasan al del grado de este. Las que alguien
 *   subió o bajó a propósito se quedan como están.
 * - Un puesto con una ficha del manual cargada no recibe copias: su perfil es
 *   el del manual, y para cambiarlo se quita antes el emparejamiento.
 */
final class CopiaDelPerfilService
{
    /** Lo que se copia del perfil: todo menos el subproceso. */
    private const CAMPOS = ['mision', 'relaciones', 'nivel', 'area_conocimiento', 'especificidad_experiencia', 'capacitacion'];

    public function __construct(private readonly PerfilDelPuestoService $perfiles)
    {
    }

    /**
     * Lo que se copiaría y lo que se reemplaza, sin tocar nada.
     *
     * @return array<string, mixed>
     */
    public function previsualizar(Puesto $destino, Puesto $origen): array
    {
        $this->cargar($origen, $destino);
        $copia = $this->copia($origen, $destino);

        return [
            'origen' => [
                'id'     => $origen->id,
                'cargo'  => $origen->cargo?->nombre,
                'unidad' => $origen->unidadAdministrativa?->nombre,
                'grado'  => $origen->grupoOcupacional?->grado_codigo,
                'mision' => $origen->perfil?->mision,
            ],
            'copia'           => $copia['contenido'],
            'omitidas'        => $copia['omitidas'],
            'ajuste_de_nivel' => $copia['ajuste'],
            'reemplaza'       => $this->perfiles->contenidos([$destino->id])[$destino->id],
            'impedimento'     => $this->impedimento($destino, $origen, $copia['contenido']),
        ];
    }

    /**
     * Copia el perfil del otro puesto en este.
     *
     * @return array{mision: bool, requisitos: int, actividades: int, competencias: int, ajuste_de_nivel: ?array{de: string, a: string, competencias: int}}
     */
    public function copiar(Puesto $destino, Puesto $origen, User $usuario): array
    {
        return DB::transaction(function () use ($destino, $origen, $usuario) {
            // Bloqueado, para que una copia y una carga del manual no
            // reemplacen el mismo perfil a la vez.
            $destino = Puesto::whereKey($destino->id)->lockForUpdate()->firstOrFail();
            $this->cargar($origen, $destino);
            $copia = $this->copia($origen, $destino);

            $motivo = $this->impedimento($destino, $origen, $copia['contenido']);
            if ($motivo !== null) {
                throw ValidationException::withMessages(['origen_id' => $motivo]);
            }

            $antes  = $this->perfiles->contenidos([$destino->id])[$destino->id];
            $perfil = $origen->perfil;

            // La misión, las relaciones, los requisitos y la capacitación.
            $this->perfiles->guardar($destino, [
                ...collect(self::CAMPOS)->mapWithKeys(fn (string $campo) => [$campo => $perfil?->getAttribute($campo)])->all(),
                'requisitos' => ($perfil?->requisitos ?? collect())
                    ->map(fn (PuestoRequisitoInstruccion $r) => [
                        'nivel_instruccion' => $r->nivel_instruccion,
                        'detalle'           => $r->detalle,
                        'meses_experiencia' => $r->meses_experiencia,
                    ])
                    ->all(),
            ], "Copia del perfil de «{$origen->cargo?->nombre}»");

            // Las actividades, en su orden.
            $destino->actividades()->delete();
            foreach ($copia['actividades'] as $i => $actividad) {
                $destino->actividades()->create([
                    'descripcion'   => $actividad->descripcion,
                    'producto'      => $actividad->producto,
                    'conocimientos' => $actividad->conocimientos,
                    'frecuencia'    => $actividad->frecuencia,
                    'orden'         => min(255, $i + 1),
                    'activo'        => true,
                ]);
            }

            $destino->competencias()->delete();
            foreach ($copia['competencias'] as $competencia) {
                $destino->competencias()->create($competencia);
            }

            $copiado = [
                'mision'          => $copia['contenido']['mision'],
                'requisitos'      => $copia['contenido']['requisitos'],
                'actividades'     => $copia['contenido']['actividades'],
                'competencias'    => $copia['contenido']['competencias'],
                'ajuste_de_nivel' => $copia['ajuste'],
            ];

            activity()
                ->performedOn($destino)
                ->causedBy($usuario)
                ->event('updated')
                ->withProperties([
                    'origen'  => [
                        'id'     => $origen->id,
                        'cargo'  => $origen->cargo?->nombre,
                        'unidad' => $origen->unidadAdministrativa?->nombre,
                    ],
                    'antes'   => $antes,
                    'copiado' => $copiado,
                ])
                ->log('Perfil copiado de otro puesto');

            return $copiado;
        });
    }

    /** Lo que hace falta de los dos puestos para copiar. */
    private function cargar(Puesto $origen, Puesto $destino): void
    {
        $origen->load([
            'cargo:id,nombre',
            'unidadAdministrativa:id,nombre',
            'grupoOcupacional',
            'perfil.requisitos',
            'actividades',
            'competencias.competencia',
        ]);
        $destino->loadMissing('grupoOcupacional');
    }

    /**
     * Lo que se copia: las actividades activas, las competencias vigentes con
     * su nivel ya ajustado al grado, y cuántas quedan fuera.
     *
     * @return array{
     *     contenido: array{mision: bool, requisitos: int, actividades: int, competencias: int},
     *     omitidas: array{actividades: int, competencias: int},
     *     ajuste: ?array{de: string, a: string, competencias: int},
     *     actividades: list<PuestoActividad>,
     *     competencias: list<array{competencia_id: int, nivel: NivelCompetencia}>,
     * }
     */
    private function copia(Puesto $origen, Puesto $destino): array
    {
        $actividades = $origen->actividades->filter(fn (PuestoActividad $a) => $a->activo)->values();
        $vigentes    = $origen->competencias->filter(fn (PuestoCompetencia $c) => (bool) $c->competencia?->activo);

        $de      = $origen->grupoOcupacional?->nivelDeCompetenciaSugerido();
        $a       = $destino->grupoOcupacional?->nivelDeCompetenciaSugerido();
        $ajustar = $de !== null && $a !== null && $de !== $a;

        $ajustadas    = 0;
        $competencias = [];
        foreach ($vigentes as $competencia) {
            $nivel = $competencia->nivel;
            if ($ajustar && $nivel === $de) {
                $nivel = $a;
                $ajustadas++;
            }
            $competencias[] = ['competencia_id' => $competencia->competencia_id, 'nivel' => $nivel];
        }

        return [
            'contenido' => [
                'mision'       => filled($origen->perfil?->mision),
                'requisitos'   => $origen->perfil?->requisitos->count() ?? 0,
                'actividades'  => $actividades->count(),
                'competencias' => count($competencias),
            ],
            'omitidas' => [
                'actividades'  => $origen->actividades->count() - $actividades->count(),
                'competencias' => $origen->competencias->count() - $vigentes->count(),
            ],
            'ajuste' => $ajustadas > 0 ? ['de' => $de->value, 'a' => $a->value, 'competencias' => $ajustadas] : null,
            'actividades'  => $actividades->all(),
            'competencias' => $competencias,
        ];
    }

    /**
     * Por qué no se puede copiar, o `null`.
     *
     * @param  array{mision: bool, requisitos: int, actividades: int, competencias: int}  $contenido
     */
    private function impedimento(Puesto $destino, Puesto $origen, array $contenido): ?string
    {
        if ($destino->id === $origen->id) {
            return 'Elija otro puesto: es el mismo.';
        }

        $ficha = FichaManualPuestos::where('puesto_id', $destino->id)->value('archivo');
        if ($ficha !== null) {
            return "Este puesto tiene cargada la ficha «{$ficha}» del Manual de Puestos. Para reemplazar su perfil, quite antes ese emparejamiento en la pestaña «Manual».";
        }

        $conTexto = collect(self::CAMPOS)->contains(fn (string $campo) => filled($origen->perfil?->getAttribute($campo)));
        if (! $conTexto && $contenido['requisitos'] === 0 && $contenido['actividades'] === 0 && $contenido['competencias'] === 0) {
            return 'El puesto elegido no tiene perfil que copiar.';
        }

        return null;
    }
}

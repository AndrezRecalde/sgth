<?php

namespace App\Services\Estructura;

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\TipoCompetencia;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoActividad;
use App\Models\Estructura\PuestoCompetencia;
use App\Models\Estructura\PuestoRequisitoInstruccion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * La ficha «Descripción y perfil del puesto» en PDF, con las diez secciones
 * del Manual de Puestos (docs/diseno-manual-de-puestos.md, P5): imprime lo
 * que el puesto tenga cargado, sin esperar a la importación del manual.
 *
 * El nombre del formato va escrito; su código no (como en la pantalla).
 */
class FichaDelPuestoPdfService
{
    /** El manual lo pone en 176 de sus 179 fichas: es el de la institución. */
    public const AMBITO = 'Provincial';

    public function __construct(
        private readonly PerfilDelPuestoService $perfiles,
        private readonly IdentidadInstitucionalServiceInterface $identidad,
    ) {
    }

    /** @return array{content: string, filename: string} */
    public function generar(Puesto $puesto): array
    {
        $pdf = Pdf::loadView('pdf.estructura.ficha-puesto', $this->datos($puesto))
            ->setPaper('a4', 'landscape');

        return [
            'content'  => $pdf->output(),
            'filename' => 'ficha_puesto_'.Str::slug($puesto->cargo?->nombre ?? 'puesto', '_')."_{$puesto->id}.pdf",
        ];
    }

    /**
     * Lo que imprime la ficha, ya en texto. Público para probar el contenido
     * sin leer el PDF.
     *
     * @return array<string, mixed>
     */
    public function datos(Puesto $puesto): array
    {
        $puesto->loadMissing(['cargo:id,nombre', 'unidadAdministrativa:id,nombre', 'grupoOcupacional']);
        $perfil = $this->perfiles->obtener($puesto);
        $requisitos = $perfil->relationLoaded('requisitos') ? $perfil->requisitos : collect();

        $competencias = $puesto->competencias()->with('competencia')->get()
            ->sortBy(fn (PuestoCompetencia $c) => $c->competencia?->numero)
            ->map(fn (PuestoCompetencia $c) => [
                'tipo'           => $c->competencia?->tipo,
                'nombre'         => $c->competencia?->nombre,
                'nivel'          => $c->nivel?->etiqueta(),
                'comportamiento' => $c->competencia && $c->nivel ? $c->competencia->comportamiento($c->nivel) : null,
                'numero'         => $c->competencia && $c->nivel ? $c->competencia->numeroDeComportamiento($c->nivel) : null,
            ]);

        $unidad = $puesto->unidadAdministrativa?->nombre;

        return [
            'identificacion' => [
                // Sin código: TH decidió que los puestos no lo lleven (D4).
                'Código'                  => null,
                'Denominación del puesto' => $puesto->cargo?->nombre,
                'Nivel'                   => $perfil->nivel?->etiqueta(),
                // «Gestión / Subproceso», como el manual (D2: el subproceso es del perfil).
                'Unidad administrativa'   => $perfil->subproceso ? "{$unidad} / {$perfil->subproceso}" : $unidad,
                'Rol'                     => $puesto->rol_puesto?->etiqueta(),
                'Grupo ocupacional'       => $puesto->grupoOcupacional?->grupo,
                'Grado'                   => $puesto->grupoOcupacional?->grado_numerico,
                'Ámbito'                  => self::AMBITO,
            ],
            'mision'       => $perfil->mision,
            'relaciones'   => $perfil->relaciones,
            'instruccion'  => $requisitos->map(fn (PuestoRequisitoInstruccion $r) => [
                'nivel'       => $r->nivel_instruccion->etiqueta().($r->detalle ? " ({$r->detalle})" : ''),
                'experiencia' => self::textoExperiencia($r->meses_experiencia),
            ])->values()->all(),
            'area'          => $perfil->area_conocimiento,
            'especificidad' => $perfil->especificidad_experiencia,
            'capacitacion'  => $perfil->capacitacion,
            'actividades'   => $puesto->actividadesActivas()->get()
                ->map(fn (PuestoActividad $a) => ['descripcion' => $a->descripcion, 'conocimientos' => $a->conocimientos])
                ->values()->all(),
            'tecnicas'      => $competencias->where('tipo', TipoCompetencia::TECNICA)->values()->all(),
            'conductuales'  => $competencias->where('tipo', TipoCompetencia::CONDUCTUAL)->values()->all(),
            ...$this->identidad->paraDocumento(),
            'fecha'         => now(),
        ];
    }

    /** «2 años y 6 meses», como lo escribe el manual. */
    public static function textoExperiencia(int $meses): string
    {
        if ($meses <= 0) {
            return 'Sin experiencia';
        }

        $anios = intdiv($meses, 12);
        $resto = $meses % 12;

        return collect([
            $anios > 0 ? $anios.' '.($anios === 1 ? 'año' : 'años') : null,
            $resto > 0 ? $resto.' '.($resto === 1 ? 'mes' : 'meses') : null,
        ])->filter()->implode(' y ');
    }
}

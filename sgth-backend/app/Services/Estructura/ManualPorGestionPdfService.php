<?php

namespace App\Services\Estructura;

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\TipoPuesto;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * El Manual de Puestos de una gestión en PDF (docs/diseno-manual-de-puestos.md,
 * P5, PR 14), como la «Distribución del Manual de Puestos 2018-2019»: una
 * portada, la distribución de los puestos por subproceso y la ficha de cada
 * uno. Sin el diagrama del proceso: lo elabora Calidad y el sistema no lo
 * adjunta (v1.6).
 *
 * Solo los puestos de plantilla activos: el manual describe los puestos con
 * plazas, no los que sostienen contratos (P9). Entran los de la gestión y los
 * de sus unidades hijas, por si alguno quedó en un subproceso.
 */
final class ManualPorGestionPdfService
{
    public function __construct(
        private readonly FichaDelPuestoPdfService $fichas,
        private readonly IdentidadInstitucionalServiceInterface $identidad,
    ) {
    }

    /** @return array{content: string, filename: string} */
    public function generar(UnidadAdministrativa $gestion): array
    {
        $pdf = Pdf::loadView('pdf.estructura.manual-gestion', $this->datos($gestion))
            ->setPaper('a4', 'landscape');

        return [
            'content'  => $pdf->output(),
            'filename' => 'manual_de_puestos_'.Str::slug($gestion->nombre, '_').'.pdf',
        ];
    }

    /** Los puestos que entran en el manual de la gestión, en el orden en que se imprimen. */
    public function puestos(UnidadAdministrativa $gestion): Collection
    {
        return Puesto::query()
            ->whereIn('unidad_administrativa_id', ArbolUnidades::conDescendientes($gestion->id))
            ->where('activo', true)
            ->where(fn ($q) => $q->whereNull('tipo')->orWhere('tipo', TipoPuesto::PLANTILLA->value))
            ->with(['cargo:id,nombre', 'unidadAdministrativa:id,nombre', 'grupoOcupacional', 'perfil:id,puesto_id,subproceso'])
            ->get()
            ->map(fn (Puesto $p) => $p->setAttribute('subproceso_del_manual', $this->subproceso($p, $gestion)))
            // Primero los de la gestión, después cada subproceso por nombre;
            // dentro, la jefatura y de mayor a menor grado.
            ->sort(fn (Puesto $a, Puesto $b) => [
                $a->subproceso_del_manual !== null, self::clave($a->subproceso_del_manual),
                ! $a->es_jefe, ! $a->grupoOcupacional?->esNivelJerarquicoSuperior(), -($a->grupoOcupacional?->grado_numerico ?? 0),
                self::clave($a->cargo?->nombre), $a->id,
            ] <=> [
                $b->subproceso_del_manual !== null, self::clave($b->subproceso_del_manual),
                ! $b->es_jefe, ! $b->grupoOcupacional?->esNivelJerarquicoSuperior(), -($b->grupoOcupacional?->grado_numerico ?? 0),
                self::clave($b->cargo?->nombre), $b->id,
            ])
            ->values();
    }

    /**
     * Lo que imprime el manual, ya en texto. Público para probar el contenido
     * sin leer el PDF.
     *
     * @return array<string, mixed>
     */
    public function datos(UnidadAdministrativa $gestion): array
    {
        $puestos = $this->puestos($gestion);

        $distribucion = $puestos
            ->groupBy(fn (Puesto $p) => $p->subproceso_del_manual ?? '')
            ->map(fn (Collection $deEste, string $subproceso) => [
                'subproceso' => $subproceso === '' ? null : $subproceso,
                'puestos'    => $deEste->map(fn (Puesto $p) => [
                    'denominacion' => $p->cargo?->nombre,
                    'grupo'        => $p->grupoOcupacional?->grupo,
                    'grado'        => $p->grupoOcupacional?->grado_codigo,
                    'plazas'       => (int) $p->plazas,
                ])->values()->all(),
                'plazas' => (int) $deEste->sum('plazas'),
            ])
            ->values()
            ->all();

        return [
            'gestion'       => $gestion->nombre,
            'distribucion'  => $distribucion,
            'total_puestos' => $puestos->count(),
            'total_plazas'  => (int) $puestos->sum('plazas'),
            'fichas'        => $puestos->map(fn (Puesto $p) => $this->fichas->contenido($p))->all(),
            ...$this->identidad->paraDocumento(),
            'fecha'         => now(),
        ];
    }

    /**
     * El subproceso del puesto: el que dice su perfil (D2) o, si quedó en una
     * unidad hija de la gestión, esa unidad. `null` si es de la gestión.
     */
    private function subproceso(Puesto $puesto, UnidadAdministrativa $gestion): ?string
    {
        if (filled($puesto->perfil?->subproceso)) {
            return trim($puesto->perfil->subproceso);
        }

        return $puesto->unidad_administrativa_id !== $gestion->id ? $puesto->unidadAdministrativa?->nombre : null;
    }

    /** Para ordenar sin que las tildes y las mayúsculas manden. */
    private static function clave(?string $texto): string
    {
        return Str::lower(Str::ascii((string) $texto));
    }
}

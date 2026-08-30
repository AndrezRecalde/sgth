<?php

namespace App\Services\Estructura;

use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Models\Estructura\UnidadAdministrativa;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * El organigrama en PDF, con sus unidades y subprocesos.
 *
 * Se arma sobre el árbol público (sin datos de personas) porque la descarga se
 * ofrece en la misma página abierta a internet donde se consulta.
 */
class OrganigramaPdfService
{
    public function __construct(
        private readonly EstructuraServiceInterface $estructuraService
    ) {}

    public function generarContent(): array
    {
        $raices = $this->estructuraService->obtenerOrganigramaPublico();

        $pdf = Pdf::loadView('pdf.estructura.organigrama', [
            'raices'      => $raices,
            'porCategoria' => $this->agruparPorCategoria($raices->first()),
            'institucion' => $raices->first(),
            'logo'        => public_path('images/logo-gadpe.png'),
            'fecha'       => now(),
        ])->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => 'organigrama_institucional_'.now()->format('Ymd_His').'.pdf',
        ];
    }

    /**
     * Las unidades del segundo nivel agrupadas por su tipo de proceso, en el
     * orden en que se leen los orgánicos del sector público: gobernantes,
     * asesores, agregadores de valor y, al final, los de apoyo.
     *
     * @return array<string, array{titulo: string, unidades: array<int, UnidadAdministrativa>}>
     */
    private function agruparPorCategoria(?UnidadAdministrativa $institucion): array
    {
        $categorias = [
            'G'   => ['titulo' => 'Procesos gobernantes',            'unidades' => []],
            'HA'  => ['titulo' => 'Procesos habilitantes de asesoría', 'unidades' => []],
            'AV'  => ['titulo' => 'Procesos agregadores de valor',    'unidades' => []],
            'HAP' => ['titulo' => 'Procesos habilitantes de apoyo',   'unidades' => []],
        ];

        foreach ($institucion?->hijos ?? [] as $unidad) {
            // Sin tipo asignado se lista como agregador de valor, que es donde
            // el organigrama gráfico ya las coloca: las dos vistas cuentan lo
            // mismo aunque el dato falte.
            $acronimo = $unidad->tipoUnidad?->acronimo ?? 'AV';
            $clave    = isset($categorias[$acronimo]) ? $acronimo : 'AV';

            $categorias[$clave]['unidades'][] = $unidad;
        }

        return array_filter($categorias, fn ($c) => $c['unidades'] !== []);
    }
}

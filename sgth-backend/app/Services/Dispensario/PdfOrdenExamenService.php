<?php

namespace App\Services\Dispensario;

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\AreaExamen;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ItemOrdenExamen;
use App\Models\Dispensario\OrdenExamen;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * La orden de exámenes en papel: la que el paciente lleva al laboratorio.
 *
 * Sigue a la receta (PdfRecetaService) en el membrete, el folio, la anulación
 * y el pie, y a las solicitudes del MSP en lo que lleva (D1): paciente,
 * servicio, profesional, prioridad, diagnóstico presuntivo, exámenes y
 * observaciones. Se firma a mano (D11).
 */
final class PdfOrdenExamenService
{
    /** El mismo establecimiento que declaran la receta y el formulario ocupacional. */
    private const DISPENSARIO = [
        'unidad'          => 'Dispensario Médico Laboral',
        'establecimiento' => 'Dispensario Médico Laboral del GADPE',
    ];

    /** @return array{content: string, filename: string} */
    public function generarContent(int $id): array
    {
        $datos = $this->datosDelImpreso($id);

        $pdf = Pdf::loadView('pdf.dispensario.orden-examenes', $datos)
            ->setPaper('a4', 'portrait');

        return [
            'content'  => $pdf->output(),
            'filename' => 'orden-examenes-' . $datos['orden']->folio . '.pdf',
        ];
    }

    /**
     * Lo que va a la plantilla, aparte de convertirlo en PDF para poder
     * comprobarlo sin abrir un binario comprimido.
     *
     * @return array<string, mixed>
     */
    public function datosDelImpreso(int $id): array
    {
        $orden = OrdenExamen::with([
            'items.examen',
            'historiaClinica.servidor',
            'historiaClinica.cargaFamiliar',
            'consultaMedica:id,especialidad',
            'solicitante.servidor',
            'diagnosticoCie10:id,codigo,descripcion',
            'anulador.servidor',
        ])->findOrFail($id);

        // El logo y los datos vigentes el día que se pidió.
        $membrete = app(IdentidadInstitucionalServiceInterface::class)->paraDocumento($orden->fecha_solicitud);

        return [
            'orden'       => $orden,
            'institucion' => [
                ...self::DISPENSARIO,
                'nombre' => $membrete['identidad']['nombre_oficial'],
                'ruc'    => $membrete['identidad']['ruc'],
            ],
            'servicio'    => $orden->consultaMedica?->especialidad?->etiqueta(),
            'paciente'    => $this->datosDelPaciente($orden->historiaClinica),
            'profesional' => $this->datosDelProfesional($orden),
            'grupos'      => $this->porArea($orden->items),
            'validaHasta' => $orden->fecha_solicitud->copy()->addDays(OrdenExamen::DIAS_VIGENCIA),
            ...$membrete,
        ];
    }

    /**
     * Los exámenes por área y en el orden de la hoja del laboratorio (D7), que
     * es como allí la leen.
     *
     * @param  Collection<int, ItemOrdenExamen>  $items
     * @return list<array{area: string, items: Collection<int, ItemOrdenExamen>}>
     */
    private function porArea(Collection $items): array
    {
        $areas = collect(AreaExamen::cases())->sortBy(fn (AreaExamen $a) => $a->orden());

        return $areas
            ->map(fn (AreaExamen $area) => [
                'area'  => $area->etiqueta(),
                'items' => $items
                    ->filter(fn (ItemOrdenExamen $i) => $i->examen?->area === $area)
                    ->sortBy(fn (ItemOrdenExamen $i) => $i->examen->orden)
                    ->values(),
            ])
            ->filter(fn (array $g) => $g['items']->isNotEmpty())
            ->values()
            ->all();
    }

    /**
     * Quién es el paciente, venga de servidor o de carga familiar. La edad y el
     * sexo, porque los valores de referencia del laboratorio dependen de ellos.
     *
     * @return array{nombre: string, cedula: ?string, edad: ?int, sexo: ?string}
     */
    private function datosDelPaciente(?HistoriaClinica $historia): array
    {
        if ($servidor = $historia?->servidor) {
            return [
                'nombre' => trim("{$servidor->nombre} {$servidor->apellido}"),
                'cedula' => $servidor->cedula,
                'edad'   => $this->edad($servidor->fecha_nacimiento),
                'sexo'   => $servidor->genero,
            ];
        }

        if ($familiar = $historia?->cargaFamiliar) {
            return [
                'nombre' => trim("{$familiar->nombres} {$familiar->apellidos}"),
                'cedula' => $familiar->cedula,
                'edad'   => $this->edad($familiar->fecha_nacimiento),
                // `cargas_familiares` no registra el sexo: en blanco antes que supuesto.
                'sexo'   => null,
            ];
        }

        return ['nombre' => '—', 'cedula' => null, 'edad' => null, 'sexo' => null];
    }

    /**
     * Quien pide, con su código ACESS: el nombre solo se repite.
     *
     * @return array{nombre: string, cedula: ?string, codigo: ?string}
     */
    private function datosDelProfesional(OrdenExamen $orden): array
    {
        $usuario  = $orden->solicitante;
        $servidor = $usuario?->servidor;

        return [
            'nombre' => $usuario?->nombre_completo
                ?? trim(($servidor?->nombre ?? '') . ' ' . ($servidor?->apellido ?? ''))
                ?: '—',
            'cedula' => $servidor?->cedula,
            'codigo' => $servidor?->codigo_medico,
        ];
    }

    private function edad(mixed $fechaNacimiento): ?int
    {
        return $fechaNacimiento ? Carbon::parse($fechaNacimiento)->age : null;
    }
}

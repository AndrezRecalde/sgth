<?php

namespace App\Services\Dispensario;

use App\Enums\CategoriaRiesgoLaboral;
use App\Enums\RegionExamenFisico;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\HistoriaClinica;
use Barryvdh\DomPDF\Facade\Pdf;

final class PdfFemoService
{
    public function __construct(
        private readonly FemoService $femoService
    ) {}

    public function generarContent(int $id): array
    {
        $ficha = $this->femoService->obtener($id);

        $examenFisicoPorRegion = $ficha->examenFisico->groupBy(
            fn ($item) => $item->region->value
        );

        // Matriz actividad x riesgo de la sección G: para cada categoría/factor
        // marcado en la ficha (en cualquiera de sus actividades), el conjunto
        // de ids de `femo_ficha_actividades` donde aparece marcado presente.
        $filasRiesgoPorCategoria = $ficha->factoresRiesgo
            ->groupBy(fn ($f) => $f->categoria->value)
            ->map(fn ($factoresCategoria) => $factoresCategoria
                ->groupBy('factor')
                ->map(fn ($factoresFactor) => $factoresFactor
                    ->filter(fn ($f) => $f->presente)
                    ->pluck('ficha_actividad_id')
                    ->filter()
                    ->unique()
                    ->values()));

        $antecedentesPorTipo = $ficha->antecedentes->groupBy(
            fn ($antecedente) => $antecedente->tipo->value
        );

        $pdf = Pdf::loadView('pdf.dispensario.femo.formulario-028', [
            'ficha' => $ficha,
            'persona' => $this->normalizarPersona($ficha),
            'examenFisicoPorRegion' => $examenFisicoPorRegion,
            'actividadesRiesgo' => $ficha->actividades,
            'filasRiesgoPorCategoria' => $filasRiesgoPorCategoria,
            'antecedentesPorTipo' => $antecedentesPorTipo,
            'regiones' => RegionExamenFisico::cases(),
            'categoriasRiesgo' => CategoriaRiesgoLaboral::cases(),
            'logo' => public_path('images/logo-gadpe.png'),
        ])->setPaper('a4', 'portrait');

        return [
            'content' => $pdf->output(),
            'filename' => 'femo_'.($ficha->numero_archivo ?: $ficha->id).'.pdf',
        ];
    }

    /**
     * Normaliza los datos de identificación de la sección A, ya que la ficha
     * puede pertenecer a un servidor (periódica/reintegro/retiro) o a un
     * postulante sin expediente todavía (ingreso).
     */
    private function normalizarPersona(FichaSaludOcupacional $ficha): object
    {
        if ($ficha->servidor) {
            $s = $ficha->servidor;

            return (object) [
                'nombre' => $s->nombre,
                'segundo_nombre' => $s->segundo_nombre,
                'apellido' => $s->apellido,
                'segundo_apellido' => $s->segundo_apellido,
                'cedula' => $s->cedula,
                'fecha_nacimiento' => $s->fecha_nacimiento,
                'genero' => $s->genero,
                'tipo_sangre' => $s->tipo_sangre,
                'tiene_enfermedad_catastrofica' => $s->tiene_enfermedad_catastrofica,
                'numero_historia' => $s->historiaClinica?->numero_historia,
            ];
        }

        $p = $ficha->postulante;
        $historia = $p ? HistoriaClinica::where('cedula_paciente', $p->cedula)->first() : null;

        return (object) [
            'nombre' => $p?->nombres,
            'segundo_nombre' => $p?->segundo_nombre,
            'apellido' => $p?->apellidos,
            'segundo_apellido' => $p?->segundo_apellido,
            'cedula' => $p?->cedula,
            'fecha_nacimiento' => $p?->fecha_nacimiento,
            'genero' => $p?->genero,
            'tipo_sangre' => $p?->tipo_sangre,
            'tiene_enfermedad_catastrofica' => false,
            'numero_historia' => $historia?->numero_historia,
        ];
    }
}

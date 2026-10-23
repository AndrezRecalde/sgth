<?php

namespace App\Http\Controllers\Dispensario;

use App\Enums\TipoExamenFemo;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\ResultadoMedico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Los resultados del paciente que la sección J de la ficha ocupacional puede
 * traer (fase 5 de docs/diseno-examenes-medicos.md, 5.6): los del último año
 * hasta la fecha de la evaluación, cada uno con la fila ya armada para copiar.
 */
final class FemoResultadosController extends Controller
{
    /** Cuánto hacia atrás cuenta un resultado para la evaluación. */
    private const MESES = 12;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            // La ficha conoce al paciente por su cédula: servidor o aspirante.
            'cedula' => ['required', 'string', 'max:20'],
            'hasta'  => ['nullable', 'date'],
        ]);

        $cedula = $request->string('cedula')->trim()->value();
        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta')) : today();

        $resultados = ResultadoMedico::query()
            ->whereHas('historiaClinica', fn (Builder $h) => $h
                ->where('cedula_paciente', $cedula)
                ->orWhereHas('servidor', fn (Builder $s) => $s->where('cedula', $cedula)))
            ->whereBetween('fecha_resultado', [
                $hasta->copy()->subMonths(self::MESES)->toDateString(),
                $hasta->toDateString(),
            ])
            ->with(ResultadoMedicoController::RELACIONES)
            ->orderByDesc('fecha_resultado')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ResultadoMedico $r) => [...$r->toArray(), 'para_femo' => $this->filaDeLaSeccionJ($r)]);

        return ApiResponse::ok($resultados, 'Resultados del paciente para la sección J.');
    }

    /**
     * La fila de la sección J: el nombre de lo que cubre (o la descripción si
     * vino sin orden), la fecha, el tipo y la lectura del médico si ya lo
     * revisó. Sin lectura, el resultado queda vacío para que lo escriba él.
     *
     * @return array{nombre_examen: string, fecha_examen: string, tipo: string, resultado: ?string, resultado_medico_id: int}
     */
    private function filaDeLaSeccionJ(ResultadoMedico $r): array
    {
        $nombres = $r->itemsOrden->map(fn ($i) => $i->examen?->nombre)->filter()->implode(', ');

        $lectura = $r->interpretacion
            ? $r->interpretacion->etiqueta() . ($r->comentario_medico ? ": {$r->comentario_medico}" : '')
            : null;

        return [
            'nombre_examen'       => mb_substr($nombres !== '' ? $nombres : $r->descripcion, 0, 200),
            'fecha_examen'        => $r->fecha_resultado->toDateString(),
            'tipo'                => TipoExamenFemo::deResultado($r->tipo)->value,
            'resultado'           => $lectura,
            'resultado_medico_id' => $r->id,
        ];
    }
}

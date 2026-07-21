<?php

namespace App\Services\Sso;

use App\Enums\FaseProgramaDrogas;
use App\Models\Sso\ProgramaDrogaActividad;
use App\Models\Sso\ProgramaDrogaSeguimiento;
use Illuminate\Database\Eloquent\Collection;

final class ProgramaDrogasService
{
    // ── Catálogo de actividades ───────────────────────────────────

    public function listarActividades(array $filtros): Collection
    {
        return ProgramaDrogaActividad::query()
            ->when(isset($filtros['fase']), fn($q) => $q->where('fase', $filtros['fase']))
            ->when(
                $filtros['solo_activas'] ?? true,
                fn($q) => $q->where('activo', true)
            )
            ->get()
            ->sortBy(fn(ProgramaDrogaActividad $a) => [$a->fase->orden(), $a->nombre])
            ->values();
    }

    public function registrarActividad(array $datos): ProgramaDrogaActividad
    {
        return ProgramaDrogaActividad::create($datos);
    }

    public function actualizarActividad(int $id, array $datos): ProgramaDrogaActividad
    {
        $actividad = ProgramaDrogaActividad::findOrFail($id);
        $actividad->update($datos);
        return $actividad->fresh();
    }

    public function eliminarActividad(int $id): void
    {
        ProgramaDrogaActividad::findOrFail($id)->delete();
    }

    // ── Seguimiento por período ───────────────────────────────────

    public function registrarSeguimiento(array $datos): ProgramaDrogaSeguimiento
    {
        $datos['registrado_por'] = auth()->id();

        return ProgramaDrogaSeguimiento::updateOrCreate(
            [
                'programa_droga_actividad_id' => $datos['programa_droga_actividad_id'],
                'periodo' => $datos['periodo'],
            ],
            [
                'estado' => $datos['estado'],
                'fecha_ejecucion' => $datos['fecha_ejecucion'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'registrado_por' => $datos['registrado_por'],
            ]
        );
    }

    /**
     * Matriz de seguimiento: todas las actividades activas del programa × su estado en el
     * período dado, agrupadas por fase y en el orden oficial (1 a 6). Las actividades sin
     * registro de seguimiento para el período se marcan como 'pendiente'.
     */
    public function listaSeguimiento(string $periodo): array
    {
        $actividades = $this->listarActividades(['solo_activas' => true]);

        $seguimientos = ProgramaDrogaSeguimiento::where('periodo', $periodo)
            ->whereIn('programa_droga_actividad_id', $actividades->pluck('id'))
            ->get()
            ->keyBy('programa_droga_actividad_id');

        $filas = $actividades->map(function (ProgramaDrogaActividad $actividad) use ($seguimientos) {
            $seguimiento = $seguimientos->get($actividad->id);

            return [
                'actividad' => $actividad,
                'seguimiento' => $seguimiento,
                'estado' => $seguimiento?->estado?->value ?? 'pendiente',
            ];
        });

        $porFase = [];
        foreach (FaseProgramaDrogas::cases() as $fase) {
            $filasFase = $filas->filter(fn($f) => $f['actividad']->fase === $fase)->values();
            $porFase[$fase->value] = [
                'etiqueta' => $fase->etiqueta(),
                'orden' => $fase->orden(),
                'filas' => $filasFase,
            ];
        }

        return [
            'periodo' => $periodo,
            'por_fase' => $porFase,
            'totales' => [
                'total' => $filas->count(),
                'ejecutada' => $filas->where('estado', 'ejecutada')->count(),
                'en_proceso' => $filas->where('estado', 'en_proceso')->count(),
                'no_ejecutada' => $filas->where('estado', 'no_ejecutada')->count(),
                'pendiente' => $filas->where('estado', 'pendiente')->count(),
            ],
        ];
    }
}

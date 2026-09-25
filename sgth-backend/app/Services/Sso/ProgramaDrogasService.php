<?php

namespace App\Services\Sso;

use App\Enums\FaseProgramaDrogas;
use App\Exceptions\ReglaNegocioException;
use App\Models\Sso\ProgramaDrogaActividad;
use App\Models\Sso\ProgramaDrogaSeguimiento;
use Illuminate\Database\Eloquent\Collection;

final class ProgramaDrogasService
{
    // ── Catálogo de actividades ───────────────────────────────────

    /**
     * Parámetros tipados y no un arreglo de filtros: `solo_activas` llegaba
     * desde la query string como la CADENA 'false' —truthy en PHP—, así que el
     * filtro se aplicaba igual y no había forma de listar una actividad
     * inactiva. Con un `bool` en la firma, el error no puede volver.
     */
    public function listarActividades(?string $fase = null, bool $soloActivas = true): Collection
    {
        return ProgramaDrogaActividad::query()
            ->when($fase !== null, fn($q) => $q->where('fase', $fase))
            ->when($soloActivas, fn($q) => $q->where('activo', true))
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

    /**
     * No se borra una actividad que ya tiene seguimiento registrado.
     *
     * La FK de `programa_drogas_seguimiento` es `cascadeOnDelete`, así que el
     * borrado se llevaba en silencio el seguimiento de todos los períodos —la
     * evidencia de las 6 fases del programa ante el MDT— sin advertirlo.
     * Marcarla inactiva la retira de la matriz y conserva lo registrado.
     */
    public function eliminarActividad(int $id): void
    {
        $actividad = ProgramaDrogaActividad::findOrFail($id);

        if ($actividad->seguimientos()->exists()) {
            throw new ReglaNegocioException(
                'No se puede eliminar la actividad porque tiene seguimiento registrado en uno o más períodos. '
                . 'Márquela como inactiva para retirarla de la matriz sin perder el historial.'
            );
        }

        $actividad->delete();
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
        $actividades = $this->listarActividades(soloActivas: true);

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

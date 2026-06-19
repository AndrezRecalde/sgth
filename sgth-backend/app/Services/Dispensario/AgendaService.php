<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\AgendaServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\AgendaMedica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AgendaService implements AgendaServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = AgendaMedica::with([
            'medico', 'servidor', 'beneficiario.servidor', 'triaje',
        ])->orderBy('fecha', 'desc')
          ->orderBy('hora_inicio', 'desc');

        if (!empty($filtros['medico_id'])) {
            $query->where('medico_id', $filtros['medico_id']);
        }

        if (!empty($filtros['fecha'])) {
            $query->whereDate('fecha', $filtros['fecha']);
        }

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    public function obtener(int $id): AgendaMedica
    {
        return AgendaMedica::with([
            'medico', 'servidor', 'beneficiario.servidor', 'triaje',
        ])->findOrFail($id);
    }

    public function agendarCita(
        array $datos,
        int $creadoPor
    ): AgendaMedica {
        return DB::transaction(function () use ($datos, $creadoPor) {
            return AgendaMedica::create([
                ...$datos,
                'estado'         => 'programada',
                'estado_registro' => true,
                'created_by'     => $creadoPor,
            ]);
        });
    }

    public function actualizar(int $id, array $datos): AgendaMedica
    {
        $agenda = AgendaMedica::findOrFail($id);
        $agenda->update($datos);
        return $agenda;
    }

    public function cancelar(int $id): AgendaMedica
    {
        $agenda = AgendaMedica::findOrFail($id);

        if ($agenda->estado === 'atendida') {
            throw new ReglaNegocioException(
                'No se puede cancelar una cita ya atendida.'
            );
        }

        $agenda->update(['estado' => 'cancelada']);
        return $agenda;
    }
}

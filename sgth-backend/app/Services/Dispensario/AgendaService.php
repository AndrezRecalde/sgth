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
            'medico', 'servidor', 'cargaFamiliar.servidor', 'triaje',
        ])->orderBy('registrado_en', 'desc');

        if (!empty($filtros['medico_id'])) {
            $query->where('medico_id', $filtros['medico_id']);
        }

        $fecha = $filtros['fecha'] ?? now()->toDateString();
        $query->whereDate('fecha', $fecha);

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['tipo_atencion'])) {
            $query->where('tipo_atencion', $filtros['tipo_atencion']);
        }

        return $query->paginate($filtros['per_page'] ?? 50);
    }

    public function obtener(int $id): AgendaMedica
    {
        return AgendaMedica::with([
            'medico', 'servidor', 'cargaFamiliar.servidor', 'triaje',
        ])->findOrFail($id);
    }

    public function agendarCita(
        array $datos,
        int $creadoPor
    ): AgendaMedica {
        return DB::transaction(function () use ($datos, $creadoPor) {
            $fecha = now()->toDateString();

            $folio = $this->generarFolio($fecha);

            return AgendaMedica::create([
                ...$datos,
                'folio'           => $folio,
                'fecha'           => $fecha,
                'registrado_en'   => now(),
                'estado'          => 'en_espera',
                'estado_registro' => true,
                'created_by'      => $creadoPor,
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
                'No se puede cancelar un turno ya atendido.'
            );
        }

        $agenda->update(['estado' => 'cancelada']);
        return $agenda;
    }

    public function listosParaConsulta(
        int $medicoId
    ): \Illuminate\Database\Eloquent\Collection {
        $turnos = AgendaMedica::with([
            'servidor', 'cargaFamiliar.servidor', 'triaje',
        ])
            ->where('medico_id', $medicoId)
            ->whereIn('estado', ['en_espera', 'en_sala'])
            ->where(function ($q) {
                $q->where('requiere_triaje', false)
                  ->orWhereHas('triaje');
            })
            ->orderBy('registrado_en', 'asc')
            ->get();

        $turnos->each(function (AgendaMedica $turno) {
            $turno->historia_clinica_id = $turno->servidor_id
                ? \App\Models\Dispensario\HistoriaClinica::where(
                    'servidor_id', $turno->servidor_id
                )->value('id')
                : \App\Models\Dispensario\HistoriaClinica::where(
                    'carga_familiar_id', $turno->carga_familiar_id
                )->value('id');
        });

        return $turnos;
    }

    private function generarFolio(string $fecha): string
    {
        $anio = substr($fecha, 0, 4);
        $cantidadActual = AgendaMedica::whereYear(
            'fecha', $anio
        )->count();
        $secuencial = str_pad(
            $cantidadActual + 1, 5, '0', STR_PAD_LEFT
        );
        return "TUR-{$anio}-{$secuencial}";
    }
}

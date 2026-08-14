<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\AgendaServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\AgendaMedica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        // 'atendido', no 'atendida': es el valor que escribe el resto del
        // módulo. Con la 'a' esta comparación nunca daba verdadera y un turno
        // ya atendido se podía cancelar igual.
        if ($agenda->estado === 'atendido') {
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

    public function turnosDelDia(
        int $medicoId,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null
    ): \Illuminate\Database\Eloquent\Collection {
        $desde = $fechaDesde ? Carbon::parse($fechaDesde) : today();
        $hasta = $fechaHasta ? Carbon::parse($fechaHasta) : $desde;

        $turnos = AgendaMedica::with([
            'servidor', 'cargaFamiliar.servidor',
            'triaje', 'consultaMedica',
        ])
            ->where('medico_id', $medicoId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderByRaw("
                CASE estado
                    WHEN 'en_consulta'    THEN 1
                    WHEN 'en_sala'        THEN 2
                    WHEN 'en_espera'      THEN 3
                    WHEN 'no_presentado'  THEN 4
                    WHEN 'atendido'       THEN 5
                    ELSE 6
                END,
                fecha ASC,
                registrado_en ASC
            ")
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

    public function marcarNoPresentado(
        int $id,
        int $usuarioId
    ): AgendaMedica {
        $turno = AgendaMedica::findOrFail($id);

        if ($turno->estado === 'atendido') {
            throw new \App\Exceptions\ReglaNegocioException(
                'No se puede marcar como no presentado un turno ya atendido.'
            );
        }

        $turno->update([
            'estado'                    => 'no_presentado',
            'marcado_no_presentado_en'  => now(),
            'marcado_no_presentado_por' => $usuarioId,
        ]);

        return $turno;
    }

    public function reactivar(
        int $id,
        int $usuarioId
    ): AgendaMedica {
        $turno = AgendaMedica::findOrFail($id);

        if ($turno->estado !== 'no_presentado') {
            throw new \App\Exceptions\ReglaNegocioException(
                'Solo se pueden reactivar turnos marcados como no presentado.'
            );
        }

        $horas = now()->diffInHours($turno->marcado_no_presentado_en);
        if ($horas > 6) {
            throw new \App\Exceptions\ReglaNegocioException(
                'No se puede reactivar un turno con más de 6 horas de ausencia.'
            );
        }

        $turno->update([
            'estado'         => 'en_espera',
            'reactivado_en'  => now(),
            'reactivado_por' => $usuarioId,
        ]);

        return $turno;
    }

    public function marcarEnConsulta(int $id): AgendaMedica
    {
        $turno = AgendaMedica::findOrFail($id);
        $turno->update(['estado' => 'en_consulta']);
        return $turno;
    }

    public function marcarAtendido(int $id): AgendaMedica
    {
        $turno = AgendaMedica::findOrFail($id);
        $turno->update(['estado' => 'atendido']);
        return $turno;
    }

    public function obtenerPorFolio(
        string $folio,
        int $medicoId
    ): AgendaMedica {
        $turno = AgendaMedica::with([
            'servidor', 'cargaFamiliar.servidor', 'triaje',
            'consultaMedica',
        ])
            ->where('folio', $folio)
            ->where('medico_id', $medicoId)
            ->firstOrFail();

        $turno->historia_clinica_id = $turno->servidor_id
            ? \App\Models\Dispensario\HistoriaClinica::where(
                'servidor_id', $turno->servidor_id
            )->value('id')
            : \App\Models\Dispensario\HistoriaClinica::where(
                'carga_familiar_id', $turno->carga_familiar_id
            )->value('id');

        return $turno;
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

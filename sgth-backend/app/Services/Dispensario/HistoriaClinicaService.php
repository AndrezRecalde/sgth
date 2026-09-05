<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Contracts\Dispensario\AgendaServiceInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class HistoriaClinicaService implements HistoriaClinicaServiceInterface
{
    public function __construct(
        private readonly AgendaServiceInterface $agendaService
    ) {}
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = HistoriaClinica::with([
            'servidor', 'cargaFamiliar.servidor',
        ])->orderBy('created_at', 'desc');

        if (!empty($filtros['servidor_id'])) {
            $query->where('servidor_id', $filtros['servidor_id']);
        }

        if (!empty($filtros['carga_familiar_id'])) {
            $query->where(
                'carga_familiar_id', $filtros['carga_familiar_id']
            );
        }

        if (!empty($filtros['cedula_paciente'])) {
            $query->where(
                'cedula_paciente', $filtros['cedula_paciente']
            );
        }

        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('cedula_paciente', 'ilike', "%{$search}%")
                  ->orWhereHas('servidor', fn($sq) =>
                      $sq->where('nombre', 'ilike', "%{$search}%")
                         ->orWhere('apellido', 'ilike', "%{$search}%")
                         ->orWhere('cedula', 'ilike', "%{$search}%")
                  )
                  ->orWhereHas('cargaFamiliar', fn($sq) =>
                      $sq->where('nombres', 'ilike', "%{$search}%")
                         ->orWhere('apellidos', 'ilike', "%{$search}%")
                         ->orWhere('cedula', 'ilike', "%{$search}%")
                  );
            });
        }

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    public function obtener(int $id): HistoriaClinica
    {
        return HistoriaClinica::with([
            'servidor', 'cargaFamiliar.servidor',
            'alergias' => fn($q) => $q->whereNull('anulado_en'),
            'antecedentes' => fn($q) => $q->whereNull('anulado_en'),
            'consultasMedicas' => fn($q) => $q
                ->orderBy('fecha_consulta', 'desc')
                ->limit(10),
        ])->findOrFail($id);
    }

    public function crearHistoria(array $datos): HistoriaClinica
    {
        $cedula = $datos['cedula_paciente']
            ?? $datos['cedula']
            ?? null;

        if ($cedula) {
            return HistoriaClinica::buscarOCrearPorCedula(
                cedula:       $cedula,
                tipoPaciente: $datos['tipo_paciente'] ?? 'servidor',
                servidorId:   $datos['servidor_id']       ?? null,
                cargaId:      $datos['carga_familiar_id'] ?? null,
                userId:       $datos['created_by']        ?? null,
            );
        }

        $existente = HistoriaClinica::where(
            'servidor_id', $datos['servidor_id'] ?? null
        )->orWhere(
            'carga_familiar_id', $datos['carga_familiar_id'] ?? null
        )->first();

        if ($existente) {
            throw new ReglaNegocioException(
                'Este paciente ya cuenta con una historia ' .
                'clínica registrada.'
            );
        }

        return HistoriaClinica::create($datos);
    }

    public function buscarPorCedula(string $cedula): ?HistoriaClinica
    {
        return HistoriaClinica::with([
            'servidor',
            'cargaFamiliar.servidor',
            'alergias'    => fn($q) => $q->whereNull('anulado_en'),
            'antecedentes'=> fn($q) => $q->whereNull('anulado_en'),
        ])->where('cedula_paciente', $cedula)->first();
    }

    public function buscarOCrearPorCedula(
        string  $cedula,
        string  $tipoPaciente = 'candidato',
        ?int    $servidorId   = null,
        ?int    $cargaId      = null,
        ?int    $userId       = null
    ): HistoriaClinica {
        return HistoriaClinica::buscarOCrearPorCedula(
            cedula:       $cedula,
            tipoPaciente: $tipoPaciente,
            servidorId:   $servidorId,
            cargaId:      $cargaId,
            userId:       $userId,
        );
    }

    /**
     * La historia clínica del paciente de un turno, abriéndola si no la tiene.
     *
     * El triaje la necesita sí o sí —la columna es NOT NULL— y hasta ahora se
     * resolvía a null cuando el paciente no tenía historia: el guardado moría
     * con un error de base de datos en la cara de la enfermera, con el paciente
     * delante. La historia no lleva ningún dato que deba teclear una persona
     * (el número es la cédula, y el dueño sale del turno), así que negarse a
     * registrar unos signos vitales por una fila que el sistema puede deducir
     * era el peor de los dos males.
     */
    public function paraPacienteDeTurno(AgendaMedica $agenda): HistoriaClinica
    {
        $esServidor = (bool) $agenda->servidor_id;
        $paciente   = $esServidor
            ? $agenda->servidor
            : $agenda->cargaFamiliar;

        if (!$paciente) {
            throw new ReglaNegocioException(
                'El turno no tiene un paciente asociado, así que no se le ' .
                'puede abrir una historia clínica.'
            );
        }

        $existente = HistoriaClinica::where(
            $esServidor ? 'servidor_id' : 'carga_familiar_id',
            $paciente->id
        )->first();

        if ($existente) {
            return $existente;
        }

        // Sin cédula no hay por dónde buscarla ni con qué numerarla, pero el
        // turno sí ancla de quién es. Es un caso de borde: el buscador de
        // pacientes trabaja por cédula, así que un paciente sin ella no llega
        // hasta aquí por la interfaz.
        if (!$paciente->cedula) {
            return HistoriaClinica::create([
                'tipo_paciente'     => $esServidor ? 'servidor' : 'familiar',
                'servidor_id'       => $esServidor ? $paciente->id : null,
                'carga_familiar_id' => $esServidor ? null : $paciente->id,
                'estado'            => true,
            ]);
        }

        $historia = HistoriaClinica::buscarOCrearPorCedula(
            cedula:       $paciente->cedula,
            tipoPaciente: $esServidor ? 'servidor' : 'familiar',
            servidorId:   $esServidor ? $paciente->id : null,
            cargaId:      $esServidor ? null : $paciente->id,
        );

        // Si la historia venía de un preocupacional existe a nombre de la
        // cédula y sin dueño, como 'candidato'. Al atenderse ya como servidor o
        // familiar se le engancha, en vez de abrirle una segunda que además
        // chocaría contra el único por cédula.
        if (!$historia->servidor_id && !$historia->carga_familiar_id) {
            $historia->update([
                'tipo_paciente'     => $esServidor ? 'servidor' : 'familiar',
                'servidor_id'       => $esServidor ? $paciente->id : null,
                'carga_familiar_id' => $esServidor ? null : $paciente->id,
            ]);
        }

        return $historia;
    }

    public function registrarConsulta(array $datos): ConsultaMedica
    {
        return DB::transaction(function () use ($datos) {
            $secundarios = $datos['diagnosticos_secundarios'] ?? [];
            $datosConsulta = Arr::except(
                $datos, ['diagnosticos_secundarios']
            );

            $consulta = ConsultaMedica::create($datosConsulta);

            foreach ($secundarios as $cie10Id) {
                \App\Models\Dispensario\DiagnosticoSecundarioConsulta::create([
                    'consulta_medica_id'  => $consulta->id,
                    'diagnostico_cie10_id' => $cie10Id,
                ]);
            }

            if (!empty($datos['agenda_medica_id'])) {
                $this->agendaService->marcarAtendido(
                    $datos['agenda_medica_id']
                );
            }

            return $consulta->load([
                'historiaClinica.servidor',
                'historiaClinica.cargaFamiliar',
                'medico',
                'diagnosticosSecundarios.diagnostico',
            ]);
        });
    }

    public function actualizarConsulta(
        int $consultaId,
        array $datos
    ): ConsultaMedica {
        return DB::transaction(function () use (
            $consultaId, $datos
        ) {
            $consulta = ConsultaMedica::findOrFail($consultaId);

            $secundarios = $datos['diagnosticos_secundarios'] ?? null;
            $datosConsulta = Arr::except(
                $datos, ['diagnosticos_secundarios']
            );

            $consulta->update($datosConsulta);

            if ($secundarios !== null) {
                \App\Models\Dispensario\DiagnosticoSecundarioConsulta::where(
                    'consulta_medica_id', $consultaId
                )->delete();

                foreach ($secundarios as $cie10Id) {
                    \App\Models\Dispensario\DiagnosticoSecundarioConsulta::create([
                        'consulta_medica_id'   => $consulta->id,
                        'diagnostico_cie10_id' => $cie10Id,
                    ]);
                }
            }

            return $consulta->load([
                'historiaClinica.servidor',
                'historiaClinica.cargaFamiliar',
                'medico',
                'diagnosticosSecundarios.diagnostico',
            ]);
        });
    }

    public function obtenerContextoConsulta(
        int $historiaClinicaId,
        ?int $agendaMedicaId = null
    ): array {
        $historia = HistoriaClinica::with([
            'servidor',
            'cargaFamiliar.servidor',
            'alergias' => fn($q) => $q->whereNull('anulado_en'),
            'antecedentes' => fn($q) => $q->whereNull('anulado_en'),
        ])->findOrFail($historiaClinicaId);

        $triajeActual = null;
        if ($agendaMedicaId) {
            $triajeActual = \App\Models\Dispensario\Triaje::where(
                'agenda_medica_id', $agendaMedicaId
            )->first();
        }

        $consultasAnteriores = ConsultaMedica::with('medico')
            ->where('historia_clinica_id', $historiaClinicaId)
            ->orderBy('fecha_consulta', 'desc')
            ->limit(3)
            ->get();

        return [
            'historia_clinica'      => $historia,
            'triaje_actual'         => $triajeActual,
            'consultas_anteriores'  => $consultasAnteriores,
        ];
    }
}

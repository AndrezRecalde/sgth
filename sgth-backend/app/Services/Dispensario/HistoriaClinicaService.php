<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Enums\EspecialidadAtencion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\BorradorConsulta;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\VersionConsultaMedica;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
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
        $servidorId = $datos['servidor_id']       ?? null;
        $cargaId    = $datos['carga_familiar_id'] ?? null;

        if (!$servidorId && !$cargaId) {
            throw new ReglaNegocioException(
                'Debe indicar de qué paciente es la historia clínica: ' .
                'un servidor o un familiar.'
            );
        }

        $cedula = $datos['cedula_paciente'] ?? $datos['cedula'] ?? null;

        // La interfaz manda solo el id del paciente, pero la historia se numera
        // por cédula y se busca por ella. Sacarla aquí evita que una historia
        // creada desde la pantalla quede sin número y sin forma de encontrarla.
        if (!$cedula) {
            $cedula = $servidorId
                ? Servidor::whereKey($servidorId)->value('cedula')
                : CargaFamiliar::whereKey($cargaId)->value('cedula');
        }

        $tipoPaciente = $datos['tipo_paciente']
            ?? ($servidorId ? 'servidor' : 'familiar');

        if ($cedula) {
            $historia = HistoriaClinica::buscarOCrearPorCedula(
                cedula:       $cedula,
                tipoPaciente: $tipoPaciente,
                servidorId:   $servidorId,
                cargaId:      $cargaId,
                userId:       $datos['created_by'] ?? null,
            );

            return $this->completarDatosClinicos($historia, $datos);
        }

        // Sin cédula solo queda el dueño. Se compara contra la clave que de
        // verdad llegó: antes se comparaba contra las dos, y `where(col, null)`
        // en Eloquent se convierte en `col IS NULL`, así que cualquier historia
        // de candidato —que no tiene servidor— hacía saltar el «ya cuenta con
        // una historia» a un paciente que no tenía ninguna.
        $existente = HistoriaClinica::where(
            $servidorId
                ? ['servidor_id' => $servidorId]
                : ['carga_familiar_id' => $cargaId]
        )->first();

        if ($existente) {
            throw new ReglaNegocioException(
                'Este paciente ya cuenta con una historia ' .
                'clínica registrada.'
            );
        }

        return HistoriaClinica::create([
            ...$datos,
            'tipo_paciente' => $tipoPaciente,
        ]);
    }

    /**
     * Los datos clínicos que trae el alta y que `buscarOCrearPorCedula` no
     * conoce. Sin esto un grupo sanguíneo enviado al crear la historia se
     * perdía por el camino sin decir nada.
     */
    private function completarDatosClinicos(
        HistoriaClinica $historia,
        array $datos
    ): HistoriaClinica {
        $extra = array_filter(
            Arr::only($datos, ['grupo_sanguineo', 'medicacion_habitual']),
            fn ($valor) => $valor !== null && $valor !== ''
        );

        if ($extra) {
            $historia->update($extra);
        }

        return $historia;
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

            $datosConsulta['especialidad'] = $this->resolverEspecialidad($datos);

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

                // Lo que se llevaba escrito ya está en la historia clínica: el
                // borrador deja de tener razón de ser, y dejarlo haría que al
                // volver a abrir el turno se ofreciera recuperar una nota que
                // ya está guardada.
                BorradorConsulta::where(
                    'agenda_medica_id', $datos['agenda_medica_id']
                )->where('medico_id', $consulta->medico_id)->delete();
            }

            return $consulta->load([
                'historiaClinica.servidor',
                'historiaClinica.cargaFamiliar',
                'medico',
                'diagnosticosSecundarios.diagnostico',
            ]);
        });
    }

    /**
     * De qué especialidad es la consulta.
     *
     * Por orden: lo que se envía explícitamente, lo que dice el turno, y el rol
     * de quien atiende. Nunca se adivina: si el profesional tiene los dos roles
     * y no hay turno que lo aclare, la consulta no se guarda sin especialidad,
     * porque una consulta sin especialidad es precisamente lo que había que
     * dejar de crear.
     *
     * @param  array<string, mixed>  $datos
     */
    private function resolverEspecialidad(array $datos): EspecialidadAtencion
    {
        if (! empty($datos['especialidad'])) {
            return EspecialidadAtencion::from($datos['especialidad']);
        }

        if (! empty($datos['agenda_medica_id'])) {
            $delTurno = AgendaMedica::whereKey($datos['agenda_medica_id'])
                ->value('tipo_atencion');

            $especialidad = EspecialidadAtencion::tryFrom((string) $delTurno);

            if ($especialidad !== null) {
                return $especialidad;
            }
        }

        $medico = User::find($datos['medico_id'] ?? null);

        $porRol = collect(EspecialidadAtencion::cases())
            ->filter(fn ($e) => $medico?->hasRole($e->rol()) ?? false);

        if ($porRol->count() === 1) {
            return $porRol->first();
        }

        throw new ReglaNegocioException(
            $porRol->count() > 1
                ? 'Indique si la consulta es de medicina general o de ' .
                  'odontología: quien atiende ejerce las dos.'
                : 'No se pudo determinar la especialidad de la consulta.'
        );
    }

    /**
     * Corrige una consulta ya guardada, dejando constancia de lo que decía.
     *
     * Quién y hasta cuándo lo decide el controlador, que es donde vive la
     * autorización. Aquí lo que importa es que **nada se pierde**: antes de
     * escribir encima se archiva la versión que se está reemplazando.
     */
    public function actualizarConsulta(
        int $consultaId,
        array $datos,
        ?int $editadoPor = null
    ): ConsultaMedica {
        return DB::transaction(function () use (
            $consultaId, $datos, $editadoPor
        ) {
            $consulta = ConsultaMedica::with('diagnosticosSecundarios')
                ->findOrFail($consultaId);

            $this->archivarVersion($consulta, $editadoPor);

            $secundarios = $datos['diagnosticos_secundarios'] ?? null;
            $datosConsulta = Arr::except(
                $datos, ['diagnosticos_secundarios']
            );

            if ($editadoPor !== null) {
                $datosConsulta['updated_by'] = $editadoPor;
            }

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

    /**
     * Archiva la nota tal y como está antes de sobrescribirla.
     *
     * Se guarda incluso cuando la corrección no cambie nada: saber que alguien
     * abrió y volvió a guardar una consulta también es información, y decidir
     * aquí qué cambio «cuenta» sería ponerle criterio a un registro clínico.
     */
    private function archivarVersion(
        ConsultaMedica $consulta,
        ?int $editadoPor
    ): void {
        if ($editadoPor === null) {
            return;
        }

        $version = Arr::only(
            $consulta->getAttributes(),
            ['diagnostico_cie10_id']
        );

        foreach (ConsultaMedica::CAMPOS_CLINICOS as $campo) {
            $version[$campo] = $consulta->{$campo};
        }

        VersionConsultaMedica::create([
            ...$version,
            'consulta_medica_id'       => $consulta->id,
            'diagnosticos_secundarios' => $consulta->diagnosticosSecundarios
                ->pluck('diagnostico_cie10_id')
                ->all(),
            'reemplazada_por'          => $editadoPor,
        ]);
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

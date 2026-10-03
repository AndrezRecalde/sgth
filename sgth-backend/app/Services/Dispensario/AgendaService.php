<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\AgendaServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\HistoriaClinica;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

final class AgendaService implements AgendaServiceInterface
{
    /**
     * Un turno sigue vivo mientras el paciente no ha entrado ni se ha ido.
     * Cancelar, marcar como no presentado o pasar a consulta solo se hace
     * desde aquí: antes cada operación miraba únicamente `atendido`, y la
     * cadena cancelada → no presentado → reactivar devolvía a la cola un turno
     * que alguien había cancelado.
     */
    private const ABIERTOS = ['en_espera', 'en_sala'];

    /**
     * El profesional con lo que pide `nombre_completo`: sin el servidor
     * cargado, el accesor hacía una consulta más por cada fila de la cola.
     */
    private const MEDICO = [
        'medico:id,usuario_ti,email,servidor_id',
        'medico.servidor:id,nombre,apellido',
    ];

    /** Horas durante las que un «no se presentó» se puede deshacer. */
    private const HORAS_PARA_REACTIVAR = 6;

    public function listar(array $filtros): LengthAwarePaginator
    {
        // Del que llegó primero al último: es el orden en que se atiende. Al
        // revés, con más de una página los primeros en quedarse fuera eran los
        // que más llevaban esperando. El id desempata dos registros del mismo
        // segundo, que sin él cambian de sitio entre una recarga y otra.
        $query = AgendaMedica::with([
            ...self::MEDICO, 'servidor', 'cargaFamiliar.servidor', 'triaje',
        ])->orderBy('registrado_en')->orderBy('id');

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
            ...self::MEDICO, 'servidor', 'cargaFamiliar.servidor', 'triaje',
        ])->findOrFail($id);
    }

    public function agendarCita(
        array $datos,
        int $creadoPor
    ): AgendaMedica {
        return DB::transaction(function () use ($datos, $creadoPor) {
            $fecha = now()->toDateString();

            $this->rechazarTurnoDuplicado($datos, $fecha);

            $folio = $this->generarFolio($fecha);

            $agenda = AgendaMedica::create([
                ...$datos,
                'folio'           => $folio,
                'fecha'           => $fecha,
                'registrado_en'   => now(),
                'estado'          => 'en_espera',
                'estado_registro' => true,
                'created_by'      => $creadoPor,
            ]);

            // Quien lo crea pasa directo a tomarle el triaje, y esa pantalla
            // muestra el nombre del paciente: sin las relaciones salía «—».
            return $agenda->load([...self::MEDICO, 'servidor', 'cargaFamiliar.servidor']);
        });
    }

    /**
     * Un mismo paciente no espera dos veces en la misma cola. Sí puede tener
     * a la vez un turno de medicina y otro de odontología, que son colas
     * distintas.
     */
    private function rechazarTurnoDuplicado(array $datos, string $fecha): void
    {
        $abierto = AgendaMedica::query()
            ->whereDate('fecha', $fecha)
            ->whereIn('estado', [...self::ABIERTOS, 'en_consulta'])
            ->where('tipo_atencion', $datos['tipo_atencion'] ?? 'medicina_general')
            ->when(
                !empty($datos['servidor_id']),
                fn ($q) => $q->where('servidor_id', $datos['servidor_id']),
                fn ($q) => $q->where('carga_familiar_id', $datos['carga_familiar_id'] ?? 0),
            )
            ->value('folio');

        if ($abierto !== null) {
            throw new ReglaNegocioException(
                "El paciente ya tiene el turno {$abierto} abierto hoy en esta cola."
            );
        }
    }

    public function cancelar(int $id): AgendaMedica
    {
        return DB::transaction(function () use ($id) {
            $agenda = AgendaMedica::lockForUpdate()->findOrFail($id);

            $this->exigirAbierto($agenda, 'cancelar');

            $agenda->update(['estado' => 'cancelada']);
            return $agenda;
        });
    }

    /**
     * Las transiciones salen de un turno abierto. Lo cerrado —atendido,
     * cancelado, no presentado— no se toca, salvo reactivar un no presentado,
     * que tiene su propia regla.
     */
    private function exigirAbierto(AgendaMedica $turno, string $accion): void
    {
        if (!in_array($turno->estado, self::ABIERTOS, true)) {
            throw new ReglaNegocioException(
                "No se puede {$accion} un turno que ya está " .
                str_replace('_', ' ', $turno->estado) . '.'
            );
        }
    }

    public function listosParaConsulta(
        int $medicoId
    ): Collection {
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

        $this->adjuntarHistoria($turnos);

        return $turnos;
    }

    public function turnosDelDia(
        int $medicoId,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null
    ): Collection {
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

        $this->adjuntarHistoria($turnos);

        return $turnos;
    }

    public function marcarNoPresentado(
        int $id,
        int $usuarioId
    ): AgendaMedica {
        return DB::transaction(function () use ($id, $usuarioId) {
            $turno = AgendaMedica::lockForUpdate()->findOrFail($id);

            $this->exigirAbierto($turno, 'marcar como no presentado');

            $turno->update([
                'estado'                    => 'no_presentado',
                'marcado_no_presentado_en'  => now(),
                'marcado_no_presentado_por' => $usuarioId,
            ]);

            return $turno;
        });
    }

    public function reactivar(
        int $id,
        int $usuarioId
    ): AgendaMedica {
        return DB::transaction(function () use ($id, $usuarioId) {
            $turno = AgendaMedica::lockForUpdate()->findOrFail($id);

            if ($turno->estado !== 'no_presentado') {
                throw new ReglaNegocioException(
                    'Solo se pueden reactivar turnos marcados como no presentado.'
                );
            }

            // Desde la marca hasta ahora, en ese orden. Con Carbon 3,
            // `now()->diffInHours($pasado)` da un número NEGATIVO, así que el
            // tope nunca saltaba y un turno de hace días volvía a la cola. Y
            // solo el mismo día: reactivar uno de ayer lo metía en la cola de
            // hoy con la fecha vieja.
            $marcado = $turno->marcado_no_presentado_en;
            if (
                $marcado === null
                || !$turno->fecha->isToday()
                || $marcado->diffInHours(now()) > self::HORAS_PARA_REACTIVAR
            ) {
                throw new ReglaNegocioException(
                    'Solo se reactiva un turno de hoy marcado hace menos de ' .
                    self::HORAS_PARA_REACTIVAR . ' horas.'
                );
            }

            $turno->update([
                'estado'         => 'en_espera',
                'reactivado_en'  => now(),
                'reactivado_por' => $usuarioId,
            ]);

            return $turno;
        });
    }

    /**
     * El médico abre la ficha del turno. Volver a abrirla es inocuo; lo que no
     * se puede es «entrar» a un turno cerrado.
     */
    public function marcarEnConsulta(int $id): AgendaMedica
    {
        return DB::transaction(function () use ($id) {
            $turno = AgendaMedica::lockForUpdate()->findOrFail($id);

            if ($turno->estado === 'en_consulta') {
                return $turno;
            }

            $this->exigirAbierto($turno, 'pasar a consulta');

            $turno->update(['estado' => 'en_consulta']);
            return $turno;
        });
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

        $this->adjuntarHistoria(new Collection([$turno]));

        return $turno;
    }


    /**
     * La historia clínica de cada turno en dos consultas, no en una por fila:
     * la cola del médico la pedía turno por turno.
     */
    private function adjuntarHistoria(Collection $turnos): void
    {
        $porServidor = HistoriaClinica::whereIn('servidor_id', $turnos->pluck('servidor_id')->filter())
            ->pluck('id', 'servidor_id');
        $porCarga = HistoriaClinica::whereIn('carga_familiar_id', $turnos->pluck('carga_familiar_id')->filter())
            ->pluck('id', 'carga_familiar_id');

        $turnos->each(function (AgendaMedica $turno) use ($porServidor, $porCarga) {
            $turno->historia_clinica_id = $turno->servidor_id
                ? $porServidor->get($turno->servidor_id)
                : $porCarga->get($turno->carga_familiar_id);
        });
    }

    /**
     * El folio sale del mayor ya emitido, no de contar filas.
     *
     * Contar acierta solo mientras no falte ninguna fila, y aquí faltan de dos
     * maneras: la tabla borra en blando —una fila retirada baja el conteo y el
     * siguiente folio repite uno ya emitido, que el índice único rechaza porque
     * el borrado en blando no libera el valor— y dos turnos pedidos a la vez
     * leen el mismo conteo. Lo segundo es lo que muerde de verdad en un
     * mostrador: dos personas admitiendo pacientes en el mismo minuto.
     *
     * Mismo arreglo que ADQ-, MED- y ENF-. El bloqueo de aviso serializa leer
     * el máximo y escribir el folio, y lo suelta el cierre de la transacción.
     */
    private function generarFolio(string $fecha): string
    {
        $anio = substr($fecha, 0, 4);

        DB::select('SELECT pg_advisory_xact_lock(?)', [
            crc32("agenda_medica_folio_{$anio}"),
        ]);

        $ultimoFolio = AgendaMedica::withTrashed()
            ->where('folio', 'like', "TUR-{$anio}-%")
            ->max('folio');

        $ultimoSecuencial = $ultimoFolio
            ? (int) substr($ultimoFolio, strlen("TUR-{$anio}-"))
            : 0;

        $secuencial = str_pad(
            (string) ($ultimoSecuencial + 1), 5, '0', STR_PAD_LEFT
        );

        return "TUR-{$anio}-{$secuencial}";
    }
}

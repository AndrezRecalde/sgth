<?php

namespace App\Services\Asistencia;

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Dispensario\CertificadoSirha7Fila;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Talento Humano y Trabajo Social aprueban los certificados médicos del
 * dispensario y los registran en Sirha7, el biométrico.
 *
 * Decidido con el usuario (2026-10-08):
 * - Se aprueba el certificado, no un permiso creado a partir de él.
 * - Aprueban admin-uath, asistente-uath y trabajo-social (la policy).
 * - Solo los del servidor titular: los de familiares no justifican ausencias.
 * - Quien aprueba elige el tipo de Sirha7, como en los permisos.
 * - Lo que el SGTH no puede escribir en Sirha7 (una cédula que Sirha7 no
 *   reconoce, o lo emitido antes de la fecha de corte) se aprueba sin Sirha7,
 *   anotando que TH lo cargó a mano. Cuenta igual en el ausentismo.
 *
 * Como en los permisos, no hay una transacción común con Sirha7: se escribe
 * allí primero y después aquí, con la fila del certificado bloqueada. El
 * procedimiento reconoce la referencia, así que un reintento no duplica.
 */
class AprobacionCertificadoSirha7Service
{
    private const POR_PAGINA_MAX = 100;

    public function __construct(private Sirha7PermisoService $sirha7) {}

    /**
     * Los certificados de servidores, para la viñeta de Asistencia.
     *
     * @param array{estado?: ?string, folio?: ?string, servidor_id?: ?int, unidad_administrativa_id?: ?int, fecha_desde?: ?string, fecha_hasta?: ?string, per_page?: ?int} $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = CertificadoMedico::query()
            ->with(['servidor.unidadAdministrativa', 'emisor.servidor', 'aprobadoPor.servidor'])
            ->whereNotNull('servidor_id');

        match ($filtros['estado'] ?? null) {
            'pendiente' => $query->whereNull('anulado_en')->whereNull('aprobado_en'),
            'aprobado'  => $query->whereNull('anulado_en')->whereNotNull('aprobado_en'),
            'anulado'   => $query->whereNotNull('anulado_en'),
            default     => null,
        };

        if (! empty($filtros['folio'])) {
            $query->where('folio', 'ilike', '%' . $filtros['folio'] . '%');
        }

        if (! empty($filtros['servidor_id'])) {
            $query->where('servidor_id', $filtros['servidor_id']);
        }

        if (! empty($filtros['unidad_administrativa_id'])) {
            $query->whereHas(
                'servidor',
                fn (Builder $s) => $s->where('unidad_administrativa_id', $filtros['unidad_administrativa_id'])
            );
        }

        // Por el reposo: un certificado entra si alguno de sus días cae en el rango.
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha_fin', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha_inicio', '<=', $filtros['fecha_hasta']);
        }

        $porPagina = min(max((int) ($filtros['per_page'] ?? 20), 1), self::POR_PAGINA_MAX);

        // Sin desempate por id, dos páginas pueden repetir filas.
        return $query->orderByDesc('fecha_inicio')->orderByDesc('id')->paginate($porPagina);
    }

    /**
     * Lo que se va a registrar, para el diálogo de aprobación.
     *
     * Con la forma de la previa de los permisos, para que el diálogo sea el
     * mismo, más si se puede escribir en Sirha7 y, si no, por qué.
     */
    public function previa(CertificadoMedico $certificado): array
    {
        $pendiente = $certificado->estaPendienteDeAprobacion();
        $registrable = $pendiente && $certificado->seRegistraDesdeElSgth();

        return [
            'pendiente'             => $pendiente,
            'motivo'                => $pendiente ? null : $this->porQueNo($certificado),
            'registrable_en_sirha7' => $registrable,
            'motivo_sin_sirha7'     => $pendiente && ! $registrable ? $this->porQueSinSirha7() : null,
            'desde'                 => $certificado->fecha_inicio->toDateString(),
            'hasta'                 => $certificado->fecha_fin->toDateString(),
            'hora_inicio'           => null,
            'hora_fin'              => null,
            'jornada_completa'      => true,
            'referencia'            => $this->referencia($certificado),
            'certificado'           => $certificado->resumenSinDatosClinicos(),
            'cruces'                => $this->cruces($certificado),
        ];
    }

    public function aprobar(int $certificadoId, int $leaveId, int $userId, ?int $servidorDelUsuario): CertificadoMedico
    {
        return DB::transaction(function () use ($certificadoId, $leaveId, $userId, $servidorDelUsuario) {
            $certificado = $this->bloquearPendiente($certificadoId, $servidorDelUsuario);

            if (! $certificado->seRegistraDesdeElSgth()) {
                throw new ReglaNegocioException($this->porQueSinSirha7());
            }

            // Mientras los certificados todavía crean su permiso: si el reposo
            // ya se registró desde el permiso, no se registra otra vez.
            $permiso = $certificado->permiso_servidor_id
                ? PermisoServidor::lockForUpdate()->find($certificado->permiso_servidor_id)
                : null;
            if ($permiso?->sirha7_aprobado_en !== null) {
                throw new ReglaNegocioException(
                    "El reposo ya se registró en Sirha7 desde el permiso {$permiso->folio}."
                );
            }

            $tipo = collect($this->sirha7->tipos())->firstWhere('id', $leaveId)
                ?? throw new ReglaNegocioException('El tipo de permiso elegido no existe en Sirha7.');

            $cedula = $certificado->servidor?->cedula
                ?? throw new ReglaNegocioException('El servidor del certificado no tiene cédula registrada.');

            $referencia = $this->referencia($certificado);

            $registro = $this->sirha7->registrar(
                $cedula,
                $tipo['id'],
                $certificado->fecha_inicio->toDateString(),
                $certificado->fecha_fin->toDateString(),
                null,
                null,
                $referencia,
            );

            foreach ($registro['filas'] as $fila) {
                CertificadoSirha7Fila::firstOrCreate(
                    ['certificado_medico_id' => $certificado->id, 'sirha7_id' => $fila['id']],
                    ['inicio' => $fila['inicio'], 'fin' => $fila['fin']],
                );
            }

            $certificado->forceFill([
                'aprobado_por'         => $userId,
                'aprobado_en'          => now(),
                'registro_sirha7'      => CertificadoMedico::REGISTRO_SGTH,
                'nota_aprobacion'      => null,
                'sirha7_leave_id'      => $tipo['id'],
                'sirha7_leave_nombre'  => $tipo['nombre'],
                'sirha7_userid'        => $registro['userid'],
                'sirha7_referencia'    => $referencia,
                'sirha7_dias_omitidos' => $registro['omitidos'] ?: null,
                'updated_by'           => $userId,
            ])->save();

            return $certificado->load('filasSirha7');
        });
    }

    /**
     * Aprobarlo sin escribir en Sirha7: TH ya lo cargó a mano allí, y lo deja
     * dicho en la nota. Sirve para las cédulas que Sirha7 no reconoce y para lo
     * emitido antes de la fecha de corte (decisión del 2026-10-08).
     */
    public function aprobarSinSirha7(int $certificadoId, string $nota, int $userId, ?int $servidorDelUsuario): CertificadoMedico
    {
        return DB::transaction(function () use ($certificadoId, $nota, $userId, $servidorDelUsuario) {
            $certificado = $this->bloquearPendiente($certificadoId, $servidorDelUsuario);

            $certificado->forceFill([
                'aprobado_por'    => $userId,
                'aprobado_en'     => now(),
                'registro_sirha7' => CertificadoMedico::REGISTRO_MANUAL,
                'nota_aprobacion' => $nota,
                'updated_by'      => $userId,
            ])->save();

            return $certificado;
        });
    }

    /**
     * Quita de Sirha7 lo que el SGTH escribió al aprobar el certificado.
     *
     * Para anularlo: con la fila ya bloqueada y dentro de la transacción de
     * quien llama. Sirha7 va primero; si se niega o no responde, la excepción
     * deshace todo y el certificado queda como estaba. Lo aprobado a mano no
     * se toca: el SGTH no lo escribió y no sabe qué hay en Sirha7.
     *
     * La aprobación en sí queda anotada; lo que se borra es el registro.
     */
    public function retirar(CertificadoMedico $certificado): void
    {
        if ($certificado->registro_sirha7 !== CertificadoMedico::REGISTRO_SGTH) {
            return;
        }

        $esperadas = $certificado->filasSirha7()->count();

        if ($esperadas > 0 && $certificado->sirha7_userid !== null && $certificado->sirha7_referencia) {
            $this->sirha7->retirar($certificado->sirha7_referencia, (int) $certificado->sirha7_userid, $esperadas);
        }

        $certificado->filasSirha7()->delete();

        $certificado->forceFill([
            'registro_sirha7'      => null,
            'sirha7_leave_id'      => null,
            'sirha7_leave_nombre'  => null,
            'sirha7_userid'        => null,
            'sirha7_referencia'    => null,
            'sirha7_dias_omitidos' => null,
        ])->save();
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    /** El certificado, bloqueado, si todavía se puede aprobar y no es de quien aprueba. */
    private function bloquearPendiente(int $certificadoId, ?int $servidorDelUsuario): CertificadoMedico
    {
        $certificado = CertificadoMedico::with('servidor')->lockForUpdate()->findOrFail($certificadoId);

        if (! $certificado->estaPendienteDeAprobacion()) {
            throw new ReglaNegocioException($this->porQueNo($certificado));
        }

        // Nadie aprueba su propio reposo. En el servicio y no en la policy:
        // admin-ti se salta la policy entera (Gate::before).
        if ($servidorDelUsuario !== null && $servidorDelUsuario === (int) $certificado->servidor_id) {
            throw new ReglaNegocioException('Nadie aprueba su propio certificado: lo debe aprobar otra persona.');
        }

        return $certificado;
    }

    /**
     * Los permisos vigentes de la persona en los días del reposo: el de la
     * consulta suele caer el primero, y lo decidido es anularlo antes de
     * aprobar (2026-10-08). El que creó el propio certificado no cuenta.
     */
    private function cruces(CertificadoMedico $certificado): array
    {
        return PermisoServidor::query()
            ->where('servidor_id', $certificado->servidor_id)
            ->when($certificado->permiso_servidor_id, fn (Builder $q, $id) => $q->whereKeyNot($id))
            ->whereDate('fecha', '>=', $certificado->fecha_inicio->toDateString())
            ->whereDate('fecha', '<=', $certificado->fecha_fin->toDateString())
            ->whereIn('estado', [
                EstadoPermiso::PENDIENTE->value,
                EstadoPermiso::ACTIVO->value,
                EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value,
            ])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->orderBy('id')
            ->get()
            ->map(fn (PermisoServidor $p) => [
                'id'          => $p->id,
                'folio'       => $p->folio,
                'tipo'        => $p->tipo instanceof TipoPermiso ? $p->tipo->value : $p->tipo,
                'fecha'       => Carbon::parse($p->fecha)->toDateString(),
                'hora_inicio' => substr((string) $p->getRawOriginal('hora_inicio'), 0, 5),
                'hora_fin'    => substr((string) $p->getRawOriginal('hora_fin'), 0, 5),
                'estado'      => $p->estado instanceof EstadoPermiso ? $p->estado->value : $p->estado,
            ])
            ->all();
    }

    private function porQueNo(CertificadoMedico $certificado): string
    {
        return match (true) {
            $certificado->servidor_id === null
                => 'Es el certificado de un familiar: no justifica ausencias y no se aprueba.',
            $certificado->anulado_en !== null
                => 'El certificado está anulado.',
            default
                => 'El certificado ya se aprobó el ' . $certificado->aprobado_en->format('d/m/Y H:i') . '.',
        };
    }

    private function porQueSinSirha7(): string
    {
        $corte = config('services.biometrico.aprobacion_permisos_desde');

        return $corte
            ? 'Se emitió antes del ' . Carbon::parse($corte)->format('d/m/Y') .
              ', cuando los reposos todavía se cargaban a mano en Sirha7. Apruébelo sin Sirha7.'
            : 'El registro en Sirha7 no está habilitado: falta la fecha de corte (SIRHA7_APROBACION_DESDE). ' .
              'Si ya lo cargó a mano, apruébelo sin Sirha7.';
    }

    /**
     * La que ya se usó si se aprobó; si no, la del folio. Guardada y no
     * deducida: con ella se retira, aunque el folio cambie de forma.
     */
    private function referencia(CertificadoMedico $certificado): string
    {
        return $certificado->sirha7_referencia ?? 'SGTH ' . $certificado->folio;
    }
}

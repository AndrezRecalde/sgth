<?php

namespace App\Services\Asistencia;

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\PermisoSirha7Fila;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aprobar un permiso del SGTH registrándolo en Sirha7, el biométrico.
 *
 * Decidido con Talento Humano (2026-10-07 y 2026-10-08):
 * - Es un paso después de la confirmación de Recepción: solo permisos activos.
 * - Personal y oficial los aprueba TH; enfermedad y calamidad, Trabajo Social,
 *   que al aprobarlas las deja además validadas: es un solo paso.
 * - Quien aprueba elige siempre el tipo de Sirha7; el SGTH no lo deduce.
 * - Solo los confirmados desde la fecha de corte: lo anterior ya lo cargó TH a
 *   mano. Ver `PermisoServidor::estaPendienteDeSirha7()`.
 * - Los reposos del dispensario no pasan por aquí: desde el 2026-10-08 se
 *   aprueba el certificado médico (`AprobacionCertificadoSirha7Service`).
 *
 * No hay una transacción común con Sirha7. Se escribe allí primero y después
 * aquí, con la fila del permiso bloqueada: si lo segundo falla, el reintento
 * no duplica, porque el procedimiento reconoce la referencia del folio y
 * devuelve lo que ya escribió.
 */
class AprobacionPermisoSirha7Service
{
    public function __construct(private Sirha7PermisoService $sirha7) {}

    /** @return list<array{id: int, nombre: string}> */
    public function tipos(): array
    {
        return $this->sirha7->tipos();
    }

    /**
     * Lo que se va a registrar, para el diálogo de aprobación: los días, si
     * son de jornada completa y los otros permisos de la persona en esos días,
     * como aviso.
     */
    public function previa(PermisoServidor $permiso): array
    {
        $rango = $this->rango($permiso);

        return [
            'pendiente'        => $permiso->estaPendienteDeSirha7(),
            'motivo'           => $permiso->estaPendienteDeSirha7() ? null : $this->porQueNo($permiso),
            'desde'            => $rango['desde'],
            'hasta'            => $rango['hasta'],
            'hora_inicio'      => $rango['hora_inicio'],
            'hora_fin'         => $rango['hora_fin'],
            'jornada_completa' => $rango['hora_inicio'] === null,
            'referencia'       => $this->referencia($permiso),
            'cruces'           => $this->cruces($permiso, $rango['desde'], $rango['hasta']),
        ];
    }

    public function aprobar(int $permisoId, int $leaveId, int $userId, ?int $servidorDelUsuario): PermisoServidor
    {
        return DB::transaction(function () use ($permisoId, $leaveId, $userId, $servidorDelUsuario) {
            $permiso = PermisoServidor::with('servidor')->lockForUpdate()->findOrFail($permisoId);

            if (! $permiso->estaPendienteDeSirha7()) {
                throw new ReglaNegocioException($this->porQueNo($permiso));
            }

            // Nadie aprueba su propio permiso. En el servicio y no en la policy:
            // admin-ti se salta la policy entera (Gate::before).
            if ($servidorDelUsuario !== null && $servidorDelUsuario === (int) $permiso->servidor_id) {
                throw new ReglaNegocioException('Nadie aprueba su propio permiso: lo debe aprobar otra persona.');
            }

            $tipo = collect($this->sirha7->tipos())->firstWhere('id', $leaveId)
                ?? throw new ReglaNegocioException('El tipo de permiso elegido no existe en Sirha7.');

            $cedula = $permiso->servidor?->cedula
                ?? throw new ReglaNegocioException('El servidor del permiso no tiene cédula registrada.');

            $rango = $this->rango($permiso);

            $registro = $this->sirha7->registrar(
                $cedula,
                $tipo['id'],
                $rango['desde'],
                $rango['hasta'],
                $rango['hora_inicio'],
                $rango['hora_fin'],
                $this->referencia($permiso),
            );

            foreach ($registro['filas'] as $fila) {
                PermisoSirha7Fila::firstOrCreate(
                    ['permiso_servidor_id' => $permiso->id, 'sirha7_id' => $fila['id']],
                    ['inicio' => $fila['inicio'], 'fin' => $fila['fin']],
                );
            }

            $permiso->fill([
                'sirha7_leave_id'      => $tipo['id'],
                'sirha7_leave_nombre'  => $tipo['nombre'],
                'sirha7_userid'        => $registro['userid'],
                'sirha7_aprobado_por'  => $userId,
                'sirha7_aprobado_en'   => now(),
                'sirha7_dias_omitidos' => $registro['omitidos'] ?: null,
            ]);

            // Trabajo Social valida y aprueba en un solo paso.
            if ($this->esDeTrabajoSocial($permiso)) {
                $permiso->estado          = EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value;
                $permiso->validado_ts_por = $userId;
                $permiso->validado_ts_en  = now();
            }

            $permiso->save();

            return $permiso->load('filasSirha7');
        });
    }

    /**
     * Quita el permiso de Sirha7 y deja de constar como aprobado.
     *
     * Lo llama revertir la confirmación, con la fila del permiso ya bloqueada
     * y dentro de su transacción (decisión del 2026-10-07: revertir retira la
     * fila de Sirha7). El certificado médico tiene su propio retiro.
     * Sirha7 va primero: si se niega o no responde, la excepción deshace la
     * transacción de quien llamó y el permiso queda como estaba. Si Sirha7 ya
     * retiró y lo de aquí falla después, repetir es seguro: el procedimiento
     * responde que no queda nada que retirar.
     *
     * Un permiso que no se aprobó en Sirha7 no se toca.
     */
    public function retirar(PermisoServidor $permiso): void
    {
        if ($permiso->sirha7_aprobado_en === null) {
            return;
        }

        $esperadas = $permiso->filasSirha7()->count();

        if ($esperadas > 0 && $permiso->sirha7_userid !== null) {
            $this->sirha7->retirar($this->referencia($permiso), (int) $permiso->sirha7_userid, $esperadas);
        }

        $permiso->filasSirha7()->delete();

        $permiso->forceFill([
            'sirha7_leave_id'      => null,
            'sirha7_leave_nombre'  => null,
            'sirha7_userid'        => null,
            'sirha7_aprobado_por'  => null,
            'sirha7_aprobado_en'   => null,
            'sirha7_dias_omitidos' => null,
        ])->save();
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    /**
     * Qué días y horas se escriben: el día del permiso, por horas; o de
     * jornada completa si cubre el día entero (00:00–23:59).
     *
     * @return array{desde: string, hasta: string, hora_inicio: ?string, hora_fin: ?string}
     */
    private function rango(PermisoServidor $permiso): array
    {
        $inicio = substr((string) $permiso->getRawOriginal('hora_inicio'), 0, 5);
        $fin    = substr((string) $permiso->getRawOriginal('hora_fin'), 0, 5);
        $diaCompleto = $inicio === '00:00' && $fin >= '23:59';
        $dia = Carbon::parse($permiso->fecha)->toDateString();

        return [
            'desde'       => $dia,
            'hasta'       => $dia,
            'hora_inicio' => $diaCompleto ? null : $inicio,
            'hora_fin'    => $diaCompleto ? null : $fin,
        ];
    }

    /** Los otros permisos vigentes de la persona en esos días. */
    private function cruces(PermisoServidor $permiso, string $desde, string $hasta): array
    {
        return PermisoServidor::query()
            ->where('servidor_id', $permiso->servidor_id)
            ->whereKeyNot($permiso->id)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereIn('estado', [
                EstadoPermiso::PENDIENTE->value,
                EstadoPermiso::ACTIVO->value,
                EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value,
            ])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
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

    private function porQueNo(PermisoServidor $permiso): string
    {
        $corte = config('services.biometrico.aprobacion_permisos_desde');

        return match (true) {
            ! $corte
                => 'La aprobación en Sirha7 no está habilitada: falta la fecha de corte (SIRHA7_APROBACION_DESDE).',
            $permiso->sirha7_aprobado_en !== null
                => 'El permiso ya se aprobó en Sirha7 el ' . $permiso->sirha7_aprobado_en->format('d/m/Y H:i') . '.',
            ($permiso->estado instanceof EstadoPermiso ? $permiso->estado : EstadoPermiso::tryFrom((string) $permiso->estado)) !== EstadoPermiso::ACTIVO
                => 'Solo se aprueba en Sirha7 un permiso confirmado por Recepción y todavía sin validar.',
            default
                => 'El permiso se confirmó antes del ' . Carbon::parse($corte)->format('d/m/Y') .
                   ', cuando los permisos todavía se cargaban a mano en Sirha7.',
        };
    }

    private function referencia(PermisoServidor $permiso): string
    {
        return 'SGTH ' . $permiso->folio;
    }

    private function esDeTrabajoSocial(PermisoServidor $permiso): bool
    {
        $tipo = $permiso->tipo instanceof TipoPermiso ? $permiso->tipo : TipoPermiso::tryFrom((string) $permiso->tipo);

        return in_array($tipo, [TipoPermiso::ENFERMEDAD, TipoPermiso::CALAMIDAD], true);
    }
}

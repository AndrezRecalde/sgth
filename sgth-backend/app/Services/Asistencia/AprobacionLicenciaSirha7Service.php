<?php

namespace App\Services\Asistencia;

use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Licencia;
use App\Models\Asistencia\LicenciaSirha7Fila;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Aprobar una licencia y registrarla en Sirha7 (fase 5.1), como los
 * certificados médicos (2026-10-08):
 *
 * - Quien aprueba elige el tipo de Sirha7 a mano.
 * - Las de salud y la calamidad las aprueba Trabajo Social; las demás,
 *   Talento Humano (`CausaLicencia::permisoParaAprobar`, en la policy).
 * - Lo que el SGTH no puede escribir se aprueba sin Sirha7, con una nota.
 * - Anularla retira de Sirha7 lo escrito.
 *
 * El procedimiento de Sirha7 no admite más de 31 días por llamada, y una
 * maternidad tiene 84. La licencia larga va en tramos parejos de hasta 31
 * días, cada uno con su referencia («SGTH LIC-2026-00001-1», «-2»…), sin tocar
 * el procedimiento. Si un tramo falla, se retiran los ya escritos.
 */
class AprobacionLicenciaSirha7Service
{
    /** Lo que admite `sp_SGTH_RegistrarPermiso` en una llamada. */
    public const DIAS_POR_TRAMO = 31;

    public function __construct(private Sirha7PermisoService $sirha7) {}

    /** Lo que se va a registrar, para el diálogo de aprobación. */
    public function previa(Licencia $licencia): array
    {
        $pendiente = $licencia->estaPendienteDeAprobacion();
        $registrable = $pendiente && $licencia->seRegistraDesdeElSgth();

        return [
            'pendiente'             => $pendiente,
            'motivo'                => $pendiente ? null : $this->porQueNo($licencia),
            'registrable_en_sirha7' => $registrable,
            'motivo_sin_sirha7'     => $pendiente && ! $registrable ? $this->porQueSinSirha7() : null,
            'desde'                 => $licencia->fecha_inicio->toDateString(),
            'hasta'                 => $licencia->fecha_fin->toDateString(),
            'jornada_completa'      => true,
            'tramos'                => $this->tramos($licencia),
        ];
    }

    public function aprobar(int $licenciaId, int $leaveId, int $userId, ?int $servidorDelUsuario): Licencia
    {
        return DB::transaction(function () use ($licenciaId, $leaveId, $userId, $servidorDelUsuario) {
            $licencia = $this->bloquearPendiente($licenciaId, $servidorDelUsuario);

            if (! $licencia->seRegistraDesdeElSgth()) {
                throw new ReglaNegocioException($this->porQueSinSirha7());
            }

            $tipo = collect($this->sirha7->tipos())->firstWhere('id', $leaveId)
                ?? throw new ReglaNegocioException('El tipo de permiso elegido no existe en Sirha7.');

            $cedula = $licencia->servidor?->cedula
                ?? throw new ReglaNegocioException('El servidor de la licencia no tiene cédula registrada.');

            $escritos = [];
            $omitidos = [];
            $userIdSirha7 = null;

            try {
                foreach ($this->tramos($licencia) as $tramo) {
                    $registro = $this->sirha7->registrar(
                        $cedula, $tipo['id'], $tramo['desde'], $tramo['hasta'], null, null, $tramo['referencia'],
                    );

                    $userIdSirha7 = $registro['userid'];
                    $escritos[] = ['referencia' => $tramo['referencia'], 'filas' => count($registro['filas'])];
                    $omitidos = [...$omitidos, ...$registro['omitidos']];

                    foreach ($registro['filas'] as $fila) {
                        LicenciaSirha7Fila::firstOrCreate(
                            ['licencia_id' => $licencia->id, 'sirha7_id' => $fila['id']],
                            ['referencia' => $tramo['referencia'], 'inicio' => $fila['inicio'], 'fin' => $fila['fin']],
                        );
                    }
                }
            } catch (Throwable $e) {
                // Sirha7 no está en esta transacción: lo que ya escribieron los
                // tramos anteriores se retira a mano antes de deshacer aquí.
                $this->retirarEscritos($escritos, $userIdSirha7);

                throw $e;
            }

            $licencia->forceFill([
                'aprobado_por'         => $userId,
                'aprobado_en'          => now(),
                'registro_sirha7'      => Licencia::REGISTRO_SGTH,
                'nota_aprobacion'      => null,
                'sirha7_leave_id'      => $tipo['id'],
                'sirha7_leave_nombre'  => $tipo['nombre'],
                'sirha7_userid'        => $userIdSirha7,
                'sirha7_dias_omitidos' => $omitidos ?: null,
                'updated_by'           => $userId,
            ])->save();

            return $licencia->load('filasSirha7');
        });
    }

    /**
     * Aprobarla sin escribir en Sirha7: TH ya la cargó a mano allí, y lo deja
     * dicho en la nota. Para las cédulas que Sirha7 no reconoce y lo
     * registrado antes de la fecha de corte.
     */
    public function aprobarSinSirha7(int $licenciaId, string $nota, int $userId, ?int $servidorDelUsuario): Licencia
    {
        return DB::transaction(function () use ($licenciaId, $nota, $userId, $servidorDelUsuario) {
            $licencia = $this->bloquearPendiente($licenciaId, $servidorDelUsuario);

            $licencia->forceFill([
                'aprobado_por'    => $userId,
                'aprobado_en'     => now(),
                'registro_sirha7' => Licencia::REGISTRO_MANUAL,
                'nota_aprobacion' => $nota,
                'updated_by'      => $userId,
            ])->save();

            return $licencia;
        });
    }

    /**
     * Quita de Sirha7 lo que el SGTH escribió, tramo por tramo. Con la fila ya
     * bloqueada y en la transacción de quien llama: si Sirha7 se niega, la
     * excepción deshace todo. Lo aprobado a mano no se toca.
     */
    public function retirar(Licencia $licencia): void
    {
        if ($licencia->registro_sirha7 !== Licencia::REGISTRO_SGTH) {
            return;
        }

        if ($licencia->sirha7_userid !== null) {
            $licencia->filasSirha7()
                ->get()
                ->groupBy('referencia')
                ->each(fn ($filas, $referencia) => $this->sirha7->retirar((string) $referencia, (int) $licencia->sirha7_userid, $filas->count()));
        }

        $licencia->filasSirha7()->delete();

        $licencia->forceFill([
            'registro_sirha7'      => null,
            'sirha7_leave_id'      => null,
            'sirha7_leave_nombre'  => null,
            'sirha7_userid'        => null,
            'sirha7_dias_omitidos' => null,
        ])->save();
    }

    /**
     * Los tramos de hasta 31 días, parejos: así el último nunca queda de uno o
     * dos días que caigan en fin de semana, donde Sirha7 no tendría nada que
     * escribir. Con uno solo, la referencia es la del folio.
     *
     * @return list<array{desde: string, hasta: string, referencia: string}>
     */
    public function tramos(Licencia $licencia): array
    {
        $dias = (int) $licencia->fecha_inicio->diffInDays($licencia->fecha_fin) + 1;
        $cuantos = (int) ceil($dias / self::DIAS_POR_TRAMO);
        $largo = (int) ceil($dias / $cuantos);

        $tramos = [];
        $desde = $licencia->fecha_inicio->copy();

        for ($i = 1; $i <= $cuantos; $i++) {
            $hasta = $desde->copy()->addDays($largo - 1)->min($licencia->fecha_fin);

            $tramos[] = [
                'desde'      => $desde->toDateString(),
                'hasta'      => $hasta->toDateString(),
                'referencia' => 'SGTH '.$licencia->folio.($cuantos > 1 ? "-{$i}" : ''),
            ];

            $desde = $hasta->copy()->addDay();
        }

        return $tramos;
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    /** @param list<array{referencia: string, filas: int}> $escritos */
    private function retirarEscritos(array $escritos, ?int $userIdSirha7): void
    {
        if ($userIdSirha7 === null) {
            return;
        }

        foreach ($escritos as $tramo) {
            try {
                $this->sirha7->retirar($tramo['referencia'], $userIdSirha7, $tramo['filas']);
            } catch (Throwable) {
                // Lo que no se pudo retirar queda con su referencia en Sirha7:
                // un reintento de la aprobación lo reconoce y no lo duplica.
            }
        }
    }

    private function bloquearPendiente(int $licenciaId, ?int $servidorDelUsuario): Licencia
    {
        $licencia = Licencia::with('servidor')->lockForUpdate()->findOrFail($licenciaId);

        if (! $licencia->estaPendienteDeAprobacion()) {
            throw new ReglaNegocioException($this->porQueNo($licencia));
        }

        if ($servidorDelUsuario !== null && $servidorDelUsuario === (int) $licencia->servidor_id) {
            throw new ReglaNegocioException('Nadie aprueba su propia licencia: la debe aprobar otra persona.');
        }

        return $licencia;
    }

    private function porQueNo(Licencia $licencia): string
    {
        return $licencia->anulado_en !== null
            ? 'La licencia está anulada.'
            : 'La licencia ya se aprobó el '.$licencia->aprobado_en->format('d/m/Y H:i').'.';
    }

    private function porQueSinSirha7(): string
    {
        $corte = config('services.biometrico.aprobacion_permisos_desde');

        return $corte
            ? 'Se registró antes del '.Carbon::parse($corte)->format('d/m/Y').
              ', cuando las ausencias todavía se cargaban a mano en Sirha7. Apruébela sin Sirha7.'
            : 'El registro en Sirha7 no está habilitado: falta la fecha de corte (SIRHA7_APROBACION_DESDE). '.
              'Si ya la cargó a mano, apruébela sin Sirha7.';
    }
}

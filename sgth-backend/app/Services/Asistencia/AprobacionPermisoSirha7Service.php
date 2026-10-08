<?php

namespace App\Services\Asistencia;

use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\PermisoSirha7Fila;
use App\Models\Dispensario\CertificadoMedico;
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
 * - Un permiso que vino de un certificado médico cubre los días de reposo del
 *   certificado: el permiso guarda solo el primero.
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
     * son de jornada completa, el certificado (sin diagnóstico) y los otros
     * permisos de la persona en esos días.
     *
     * Los cruces son un aviso: el permiso de la consulta y el reposo del
     * certificado suelen caer el mismo primer día, y lo decidido es que Trabajo
     * Social anule el de la consulta antes de aprobar (2026-10-08).
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
            'certificado'      => $rango['certificado'] ? $this->datosDelCertificado($rango['certificado']) : null,
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

    // ── Apoyos ───────────────────────────────────────────────────────

    /**
     * Qué días y horas se escriben.
     *
     * Del certificado médico, si lo hay y no está anulado: sus días de reposo,
     * de jornada completa. Si no, el día del permiso, por horas; o de jornada
     * completa si cubre el día entero (00:00–23:59), como los del dispensario.
     *
     * @return array{desde: string, hasta: string, hora_inicio: ?string, hora_fin: ?string, certificado: ?CertificadoMedico}
     */
    private function rango(PermisoServidor $permiso): array
    {
        $certificado = $permiso->certificadoMedico()->whereNull('anulado_en')->first();

        if ($certificado && $certificado->fecha_inicio && $certificado->fecha_fin) {
            return [
                'desde'       => $certificado->fecha_inicio->toDateString(),
                'hasta'       => $certificado->fecha_fin->toDateString(),
                'hora_inicio' => null,
                'hora_fin'    => null,
                'certificado' => $certificado,
            ];
        }

        $inicio = substr((string) $permiso->getRawOriginal('hora_inicio'), 0, 5);
        $fin    = substr((string) $permiso->getRawOriginal('hora_fin'), 0, 5);
        $diaCompleto = $inicio === '00:00' && $fin >= '23:59';
        $dia = Carbon::parse($permiso->fecha)->toDateString();

        return [
            'desde'       => $dia,
            'hasta'       => $dia,
            'hora_inicio' => $diaCompleto ? null : $inicio,
            'hora_fin'    => $diaCompleto ? null : $fin,
            'certificado' => null,
        ];
    }

    /**
     * Lo que Trabajo Social ve del certificado: fechas, días, médico y folio.
     * Ni diagnóstico ni observaciones, que son datos de salud (decisión del
     * 2026-10-08).
     */
    private function datosDelCertificado(CertificadoMedico $certificado): array
    {
        $certificado->loadMissing('emisor.servidor');
        $medico = $certificado->emisor?->servidor;

        return [
            'folio'        => $certificado->folio,
            'fecha_inicio' => $certificado->fecha_inicio?->toDateString(),
            'fecha_fin'    => $certificado->fecha_fin?->toDateString(),
            'dias_reposo'  => $certificado->dias_reposo,
            'medico'       => $medico
                ? trim("{$medico->apellido} {$medico->nombre}")
                : $certificado->emisor?->usuario_ti,
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

<?php

namespace App\Services\Expediente;

use App\Enums\EstadoSolicitudAccion;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\TipoSolicitudAccion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\SolicitudAccionPersonal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Las solicitudes del servidor desde el portal: renuncia, licencia sin
 * remuneración y jubilación (diseño, 9.13; fase 6.3b).
 *
 * El servidor presenta la suya con su respaldo en PDF y la retira mientras
 * está pendiente. Talento Humano la atiende preparando la acción por el mismo
 * camino que el formulario —así aplican todas sus reglas— o la rechaza con el
 * motivo. Son solo de los vínculos de la LOSEP: el obrero tiene el desahucio
 * (Código del Trabajo, Art. 184), y las autoridades y Servicios Profesionales,
 * sus propias salidas.
 */
class SolicitudAccionPersonalService
{
    public function __construct(private readonly MovimientoPersonalService $movimientos) {}

    // ── El servidor ──────────────────────────────────────────────

    /**
     * @param  array{tipo: string, causal?: ?string, fecha_desde: string, fecha_hasta?: ?string, motivo?: ?string}  $datos
     */
    public function presentar(User $usuario, array $datos, UploadedFile $archivo): SolicitudAccionPersonal
    {
        $servidorId = $usuario->servidor_id
            ?? throw new ReglaNegocioException('Su cuenta no está vinculada a un servidor: no puede presentar solicitudes.');

        $tipo = TipoSolicitudAccion::from($datos['tipo']);
        $causal = $tipo->causalFija()
            ?? (filled($datos['causal'] ?? null) ? SubtipoMovimientoPersonal::tryFrom((string) $datos['causal']) : null);
        $desde = Carbon::parse($datos['fecha_desde'])->startOfDay();
        $hasta = filled($datos['fecha_hasta'] ?? null) ? Carbon::parse($datos['fecha_hasta'])->startOfDay() : null;

        $this->exigirQuePuedaPedirla($servidorId, $tipo, $causal);
        $this->validarFechas($tipo, $desde, $hasta);

        return DB::transaction(function () use ($usuario, $servidorId, $tipo, $causal, $desde, $hasta, $datos, $archivo) {
            // Una a la vez de cada tipo: dos renuncias pendientes no son dos actos.
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("solicitud_accion_servidor_{$servidorId}")]);

            $pendiente = SolicitudAccionPersonal::where('servidor_id', $servidorId)
                ->where('tipo', $tipo->value)
                ->where('estado', EstadoSolicitudAccion::PENDIENTE->value)
                ->first();

            if ($pendiente) {
                throw new ReglaNegocioException(
                    "Ya tiene una solicitud de {$tipo->etiqueta()} pendiente ({$pendiente->folio}): retírela si quiere presentar otra."
                );
            }

            $ruta = $archivo->store('expediente/solicitudes/'.now()->year, 'local');

            if (! $ruta) {
                throw new ReglaNegocioException('No se pudo guardar el respaldo. Inténtelo de nuevo.');
            }

            return SolicitudAccionPersonal::create([
                'folio'              => $this->generarFolio(),
                'servidor_id'        => $servidorId,
                'tipo'               => $tipo->value,
                'causal'             => $tipo === TipoSolicitudAccion::LICENCIA_SIN_REMUNERACION ? $causal?->value : null,
                'fecha_presentacion' => now()->toDateString(),
                'fecha_desde'        => $desde->toDateString(),
                'fecha_hasta'        => $hasta?->toDateString(),
                'motivo'             => filled($datos['motivo'] ?? null) ? trim((string) $datos['motivo']) : null,
                'documento_ruta'     => $ruta,
                'documento_nombre'   => $archivo->getClientOriginalName(),
                'estado'             => EstadoSolicitudAccion::PENDIENTE->value,
                'created_by'         => $usuario->id,
            ])->fresh();
        });
    }

    /** La retira mientras está pendiente: es suya, y todavía no es nada. */
    public function retirar(User $usuario, int $solicitudId): SolicitudAccionPersonal
    {
        $solicitud = $this->deLaCuenta($usuario, $solicitudId);

        if ($solicitud->estado !== EstadoSolicitudAccion::PENDIENTE) {
            throw new ReglaNegocioException("La solicitud está {$this->estadoEnPalabras($solicitud)}: ya no se retira.");
        }

        $solicitud->forceFill([
            'estado'      => EstadoSolicitudAccion::RETIRADA,
            'retirada_en' => now(),
        ])->save();

        return $solicitud->fresh();
    }

    /** @return Collection<int, SolicitudAccionPersonal> */
    public function delServidor(int $servidorId): Collection
    {
        return SolicitudAccionPersonal::with('movimientoPersonal')
            ->where('servidor_id', $servidorId)
            ->orderByDesc('fecha_presentacion')
            ->orderByDesc('id')
            ->get();
    }

    /** Una de las suyas, o 404. */
    public function deLaCuenta(User $usuario, int $solicitudId): SolicitudAccionPersonal
    {
        return SolicitudAccionPersonal::where('servidor_id', $usuario->servidor_id ?? 0)->findOrFail($solicitudId);
    }

    // ── Talento Humano ───────────────────────────────────────────

    /**
     * Las del portal, las pendientes primero y, entre ellas, las más antiguas:
     * una renuncia sin respuesta se tiene por aceptada a los 15 días.
     *
     * @return Collection<int, SolicitudAccionPersonal>
     */
    public function paraTalentoHumano(?string $estado = null): Collection
    {
        return SolicitudAccionPersonal::with(['servidor', 'movimientoPersonal', 'atendidaPor.servidor'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")
            ->orderBy('fecha_presentacion')
            ->orderBy('id')
            ->get();
    }

    /**
     * Prepara su acción de personal en borrador, por el mismo camino que el
     * formulario: la clase, la causal, las fechas y la referencia de la
     * solicitud. Talento Humano la revisa y la corrige en el borrador.
     */
    public function preparar(int $solicitudId, User $usuario): SolicitudAccionPersonal
    {
        return DB::transaction(function () use ($solicitudId, $usuario) {
            $solicitud = SolicitudAccionPersonal::lockForUpdate()->findOrFail($solicitudId);

            $this->exigirPendiente($solicitud);
            TramiteSobreSiMismo::impedir($solicitud->servidor_id);

            $movimiento = $this->movimientos->registrarPorClase(
                $solicitud->servidor_id,
                $solicitud->tipo->clase(),
                $solicitud->causalDeLaAccion()?->value,
                $this->datosDeLaAccion($solicitud),
            );

            $solicitud->forceFill([
                'estado'                 => EstadoSolicitudAccion::ATENDIDA,
                'movimiento_personal_id' => $movimiento->id,
                'atendida_por'           => $usuario->id,
                'atendida_en'            => now(),
            ])->save();

            return $solicitud->fresh(['movimientoPersonal']);
        });
    }

    /**
     * La rechaza con el motivo, que el servidor ve en su portal. La renuncia
     * que ya se tiene por aceptada no se rechaza (Reglamento a la LOSEP,
     * Art. 102).
     */
    public function rechazar(int $solicitudId, string $motivo, User $usuario): SolicitudAccionPersonal
    {
        return DB::transaction(function () use ($solicitudId, $motivo, $usuario) {
            $solicitud = SolicitudAccionPersonal::lockForUpdate()->findOrFail($solicitudId);

            $this->exigirPendiente($solicitud);
            TramiteSobreSiMismo::impedir($solicitud->servidor_id);

            if ($solicitud->aceptadaPorElPlazo()) {
                throw new ReglaNegocioException(
                    'Pasaron 15 días sin respuesta: la renuncia se tiene por aceptada (Reglamento a la LOSEP, Art. 102). '
                        .'Prepare la acción.'
                );
            }

            $solicitud->forceFill([
                'estado'         => EstadoSolicitudAccion::RECHAZADA,
                'motivo_rechazo' => trim($motivo),
                'atendida_por'   => $usuario->id,
                'atendida_en'    => now(),
            ])->save();

            return $solicitud->fresh();
        });
    }

    // ── Reglas ───────────────────────────────────────────────────

    /**
     * Solo los vínculos de la LOSEP, y la clase tiene que aplicar a su
     * nombramiento: la licencia sin remuneración, solo a los permanentes (fase
     * 2.2). La causal de la licencia es una de las suyas.
     */
    private function exigirQuePuedaPedirla(int $servidorId, TipoSolicitudAccion $tipo, ?SubtipoMovimientoPersonal $causal): void
    {
        $nombramiento = Servidor::with('contratoVigente')->findOrFail($servidorId)->contratoVigente?->tipo_nombramiento;

        if (! $nombramiento instanceof TipoNombramiento) {
            throw new ReglaNegocioException('No tiene un vínculo vigente: no hay solicitud que presentar.');
        }

        if (! $nombramiento->esLosep() || $nombramiento === TipoNombramiento::ELECCION_POPULAR) {
            throw new ReglaNegocioException(
                'Estas solicitudes son de los servidores de la LOSEP. Su vínculo ('.$nombramiento->etiqueta()
                    .') tiene su propio trámite: consulte a Talento Humano.'
            );
        }

        $clase = $tipo->clase();

        if ($tipo === TipoSolicitudAccion::LICENCIA_SIN_REMUNERACION
            && (! $causal || ! in_array($causal, $clase->causales(), true))
        ) {
            throw new ReglaNegocioException('Indique la causal de la licencia sin remuneración.');
        }

        if (! $clase->elegiblePara($nombramiento, $causal)) {
            throw new ReglaNegocioException(
                "La {$tipo->etiqueta()} no aplica a su nombramiento ({$nombramiento->etiqueta()})."
            );
        }
    }

    private function validarFechas(TipoSolicitudAccion $tipo, Carbon $desde, ?Carbon $hasta): void
    {
        $hoy = now()->startOfDay();

        if ($tipo === TipoSolicitudAccion::RENUNCIA) {
            $minima = $hoy->copy()->addDays(SolicitudAccionPersonal::DIAS_DE_LA_RENUNCIA);

            if ($desde->lt($minima)) {
                throw new ReglaNegocioException(
                    'La renuncia se presenta con 15 días de anticipación (Reglamento a la LOSEP, Art. 102): '
                        .'la fecha más próxima en que puede dejar el puesto es el '.$minima->format('d/m/Y').'.'
                );
            }

            return;
        }

        if ($desde->lt($hoy)) {
            throw new ReglaNegocioException('La fecha de inicio no puede ser anterior a hoy.');
        }

        if ($tipo === TipoSolicitudAccion::LICENCIA_SIN_REMUNERACION && (! $hasta || $hasta->lt($desde))) {
            throw new ReglaNegocioException('Indique hasta cuándo es la licencia: no antes de su inicio.');
        }
    }

    private function exigirPendiente(SolicitudAccionPersonal $solicitud): void
    {
        if ($solicitud->estado !== EstadoSolicitudAccion::PENDIENTE) {
            throw new ReglaNegocioException("La solicitud {$solicitud->folio} está {$this->estadoEnPalabras($solicitud)}.");
        }
    }

    /** @return array<string, mixed> */
    private function datosDeLaAccion(SolicitudAccionPersonal $solicitud): array
    {
        $esLicencia = $solicitud->tipo === TipoSolicitudAccion::LICENCIA_SIN_REMUNERACION;

        return [
            'descripcion' => "Atiende la solicitud {$solicitud->folio} que el servidor presentó desde el portal el "
                .$solicitud->fecha_presentacion->format('d/m/Y').'.'
                .($solicitud->motivo ? ' Motivo: '.$solicitud->motivo : ''),
            'fecha_efectiva'    => $solicitud->fecha_desde->toDateString(),
            'fecha_inicio'      => $esLicencia ? $solicitud->fecha_desde->toDateString() : null,
            'fecha_fin'         => $esLicencia ? $solicitud->fecha_hasta?->toDateString() : null,
            // La referencia que imprime la explicación del documento.
            'resolucion_numero' => "Solicitud {$solicitud->folio}",
        ];
    }

    private function estadoEnPalabras(SolicitudAccionPersonal $solicitud): string
    {
        return mb_strtolower($solicitud->estado->etiqueta());
    }

    private function generarFolio(): string
    {
        $anio = now()->year;

        DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("solicitud_accion_folio_{$anio}")]);

        $ultimo = SolicitudAccionPersonal::where('folio', 'like', "SOL-{$anio}-%")->max('folio');
        $secuencial = $ultimo ? (int) substr($ultimo, strlen("SOL-{$anio}-")) : 0;

        return "SOL-{$anio}-".str_pad((string) ($secuencial + 1), 5, '0', STR_PAD_LEFT);
    }
}

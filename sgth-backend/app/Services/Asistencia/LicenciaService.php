<?php

namespace App\Services\Asistencia;

use App\Enums\CausaLicencia;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoPermiso;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Licencia;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Las licencias con remuneración, fuera de las vacaciones (diseño de Acciones
 * de Personal, 4.4 y 9.4; TH N12; fase 5.1).
 *
 * - Las registra Talento Humano con su causa, sus fechas y el PDF del
 *   respaldo, sin acción de personal y sin descontar vacaciones.
 * - La causa tiene su tope (`CausaLicencia::tope`): pasado, no se registra.
 * - Ocupa todos sus días desde que se registra. No cabe encima de vacaciones,
 *   permisos, un reposo del dispensario, otra licencia o una ausencia de
 *   acción de personal (comisión, licencia sin remuneración); y al revés.
 * - Se aprueba y se registra en Sirha7 aparte (`AprobacionLicenciaSirha7Service`).
 */
class LicenciaService
{
    private const POR_PAGINA_MAX = 100;

    /** Los que no tienen licencias con remuneración: no son una relación laboral de este tipo. */
    private const SIN_LICENCIAS = [TipoNombramiento::SERVICIOS_PROFESIONALES, TipoNombramiento::ELECCION_POPULAR];

    public function __construct(private AprobacionLicenciaSirha7Service $aprobacion) {}

    /**
     * @param array{estado?: ?string, causa?: ?string, causas?: ?list<string>, folio?: ?string, servidor_id?: ?int,
     *              unidad_administrativa_id?: ?int, fecha_desde?: ?string, fecha_hasta?: ?string, per_page?: ?int} $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = Licencia::query()->with(['servidor.unidadAdministrativa', 'aprobadoPor.servidor', 'creadoPor.servidor']);

        match ($filtros['estado'] ?? null) {
            'pendiente' => $query->whereNull('anulado_en')->whereNull('aprobado_en'),
            'aprobada'  => $query->whereNull('anulado_en')->whereNotNull('aprobado_en'),
            'anulada'   => $query->whereNotNull('anulado_en'),
            default     => null,
        };

        if (! empty($filtros['causa'])) {
            $query->where('causa', $filtros['causa']);
        }

        // Quien solo aprueba algunas causas ve solo esas.
        if (isset($filtros['causas'])) {
            $query->whereIn('causa', $filtros['causas']);
        }

        if (! empty($filtros['folio'])) {
            $query->where('folio', 'ilike', '%'.$filtros['folio'].'%');
        }

        if (! empty($filtros['servidor_id'])) {
            $query->where('servidor_id', $filtros['servidor_id']);
        }

        if (! empty($filtros['unidad_administrativa_id'])) {
            $query->whereHas('servidor', fn (Builder $s) => $s->where('unidad_administrativa_id', $filtros['unidad_administrativa_id']));
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha_fin', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha_inicio', '<=', $filtros['fecha_hasta']);
        }

        $porPagina = min(max((int) ($filtros['per_page'] ?? 20), 1), self::POR_PAGINA_MAX);

        return $query->orderByDesc('fecha_inicio')->orderByDesc('id')->paginate($porPagina);
    }

    /**
     * Registra la licencia, pendiente de aprobación.
     *
     * @param array{servidor_id: int, causa: string, alcance?: ?string, fecha_inicio: string, fecha_fin: string, observacion?: ?string} $datos
     */
    public function registrar(array $datos, UploadedFile $archivo, int $userId, ?int $servidorDelUsuario): Licencia
    {
        $servidorId = (int) $datos['servidor_id'];

        // Nadie se registra su propia licencia. En el servicio y no en la
        // policy: admin-ti se salta la policy entera (Gate::before).
        if ($servidorDelUsuario !== null && $servidorDelUsuario === $servidorId) {
            throw new ReglaNegocioException('Nadie registra su propia licencia: la debe registrar otra persona de Talento Humano.');
        }

        $causa = CausaLicencia::from($datos['causa']);
        $alcance = filled($datos['alcance'] ?? null) ? (string) $datos['alcance'] : null;
        $inicio = Carbon::parse($datos['fecha_inicio'])->startOfDay();
        $fin = Carbon::parse($datos['fecha_fin'])->startOfDay();

        $this->exigirVinculo($servidorId);
        $this->validarAlcance($causa, $alcance);
        $dias = (int) $inicio->diffInDays($fin) + 1;
        $this->validarTope($causa, $alcance, $dias);

        return DB::transaction(function () use ($datos, $archivo, $userId, $servidorId, $causa, $alcance, $inicio, $fin, $dias) {
            // El mismo bloqueo que las vacaciones del servidor: una licencia y
            // unas vacaciones a la vez no pasan las dos el cruce.
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("vacaciones_servidor_{$servidorId}")]);

            $this->rechazarSiSeCruza($servidorId, $inicio, $fin);

            $ruta = $archivo->store('asistencia/licencias/'.$inicio->year, 'local');

            if (! $ruta) {
                throw new ReglaNegocioException('No se pudo guardar el respaldo. Inténtelo de nuevo.');
            }

            return Licencia::create([
                'folio'            => $this->generarFolio(),
                'servidor_id'      => $servidorId,
                'causa'            => $causa->value,
                'alcance'          => $alcance,
                'fecha_inicio'     => $inicio->toDateString(),
                'fecha_fin'        => $fin->toDateString(),
                'dias'             => $dias,
                'observacion'      => filled($datos['observacion'] ?? null) ? trim((string) $datos['observacion']) : null,
                'documento_ruta'   => $ruta,
                'documento_nombre' => $archivo->getClientOriginalName(),
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ]);
        });
    }

    /**
     * La anula, con su motivo. Si se registró en Sirha7, lo retira primero: si
     * Sirha7 se niega o no responde, la licencia queda como estaba.
     */
    public function anular(int $licenciaId, string $motivo, int $userId): Licencia
    {
        return DB::transaction(function () use ($licenciaId, $motivo, $userId) {
            $licencia = Licencia::lockForUpdate()->findOrFail($licenciaId);

            if ($licencia->anulado_en !== null) {
                throw new ReglaNegocioException('La licencia ya está anulada.');
            }

            $this->aprobacion->retirar($licencia);

            $licencia->forceFill([
                'anulado_por'      => $userId,
                'anulado_en'       => now(),
                'motivo_anulacion' => $motivo,
                'updated_by'       => $userId,
            ])->save();

            return $licencia->fresh();
        });
    }

    // ── Reglas ───────────────────────────────────────────────────────

    private function exigirVinculo(int $servidorId): void
    {
        $vigente = Servidor::with('contratoVigente')->findOrFail($servidorId)->contratoVigente;

        if (! $vigente) {
            throw new ReglaNegocioException('El servidor no tiene un vínculo vigente: no hay licencia que registrarle.');
        }

        if (in_array($vigente->tipo_nombramiento, self::SIN_LICENCIAS, true)) {
            throw new ReglaNegocioException(
                $vigente->tipo_nombramiento === TipoNombramiento::ELECCION_POPULAR
                    ? 'La licencia de una autoridad electa la concede el Consejo Provincial: regístrela en Acciones de Personal.'
                    : 'Servicios Profesionales es un contrato civil: no tiene licencias con remuneración.'
            );
        }
    }

    private function validarAlcance(CausaLicencia $causa, ?string $alcance): void
    {
        if ($alcance === null) {
            if ($causa->exigeAlcance()) {
                throw new ReglaNegocioException("Indique de quién es la {$causa->etiqueta()}: el tope depende de eso.");
            }

            return;
        }

        if (! array_key_exists($alcance, $causa->alcances())) {
            throw new ReglaNegocioException("Ese caso no corresponde a la licencia por {$causa->etiqueta()}.");
        }
    }

    private function validarTope(CausaLicencia $causa, ?string $alcance, int $dias): void
    {
        $tope = $causa->tope($alcance);

        if ($tope !== null && $dias > $tope) {
            throw new ReglaNegocioException(
                "La licencia por {$causa->etiqueta()} admite hasta {$tope} días ({$causa->baseLegal()}), "
                    ."y las fechas suman {$dias}."
            );
        }
    }

    /**
     * Sobre los días de la licencia no hay otra ausencia del mismo servidor.
     */
    public function rechazarSiSeCruza(int $servidorId, Carbon $inicio, Carbon $fin, ?int $exceptoLicenciaId = null): void
    {
        $desde = $inicio->toDateString();
        $hasta = $fin->toDateString();
        $fmt = fn ($f) => Carbon::parse($f)->format('d/m/Y');

        $otra = Licencia::vigentesDe($servidorId, $desde, $hasta)
            ->when($exceptoLicenciaId, fn ($q) => $q->where('id', '!=', $exceptoLicenciaId))
            ->first();

        if ($otra) {
            throw new ReglaNegocioException(
                "Las fechas se cruzan con la licencia {$otra->folio} ({$otra->causa->etiqueta()}), del "
                    .$fmt($otra->fecha_inicio).' al '.$fmt($otra->fecha_fin).'.'
            );
        }

        $vacacion = Vacacion::where('servidor_id', $servidorId)
            ->whereIn('estado', ['pendiente', 'aprobada', 'gozada'])
            ->whereDate('fecha_inicio', '<=', $hasta)
            ->whereDate('fecha_fin', '>=', $desde)
            ->orderBy('fecha_inicio')
            ->first();

        if ($vacacion) {
            throw new ReglaNegocioException(
                'Las fechas se cruzan con las vacaciones '.($vacacion->folio ?? "#{$vacacion->id}").' ('.$vacacion->estado.'), del '
                    .$fmt($vacacion->fecha_inicio).' al '.$fmt($vacacion->fecha_fin).': anúlelas o elija otras fechas.'
            );
        }

        $permiso = PermisoServidor::where('servidor_id', $servidorId)
            ->whereIn('estado', [
                EstadoPermiso::PENDIENTE->value,
                EstadoPermiso::ACTIVO->value,
                EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value,
            ])
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha')
            ->first();

        if ($permiso) {
            throw new ReglaNegocioException(
                'Las fechas incluyen el permiso '.($permiso->folio ?? "#{$permiso->id}").' del '
                    .$fmt($permiso->fecha).': anúlelo o elija otras fechas.'
            );
        }

        $reposo = CertificadoMedico::reposoDe($servidorId, $desde, $hasta)->first();

        if ($reposo) {
            throw new ReglaNegocioException(
                "Las fechas incluyen el reposo médico del certificado {$reposo->folio}, del "
                    .$fmt($reposo->fecha_inicio).' al '.$fmt($reposo->fecha_fin).': esos días ya tienen una ausencia.'
            );
        }

        // Las de acción de personal: comisión, licencia sin remuneración o del
        // Consejo. Su fin real lo da el reintegro, si lo hubo. Por query(): el
        // modelo tiene también un esAusenciaTemporal() de instancia.
        $ausencia = MovimientoPersonal::query()->esAusenciaTemporal()
            ->where('servidor_id', $servidorId)
            ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
            ->whereDate('fecha_inicio', '<=', $hasta)
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get()
            ->first(fn (MovimientoPersonal $m) => ($m->finEfectivo()?->toDateString() ?? '9999-12-31') >= $desde);

        if ($ausencia) {
            throw new ReglaNegocioException(
                "Las fechas se cruzan con la {$ausencia->etiquetaAusencia()} {$ausencia->codigo_registro}, desde el "
                    .$fmt($ausencia->fecha_inicio).': el servidor está ausente por acción de personal.'
            );
        }
    }

    /**
     * LIC-AAAA-NNNNN, como el folio de las vacaciones: el máximo, bajo un
     * bloqueo de aviso que suelta el cierre de la transacción.
     */
    private function generarFolio(): string
    {
        $anio = now()->year;

        DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("licencia_folio_{$anio}")]);

        $ultimo = Licencia::where('folio', 'like', "LIC-{$anio}-%")->max('folio');
        $secuencial = $ultimo ? (int) substr($ultimo, strlen("LIC-{$anio}-")) : 0;

        return "LIC-{$anio}-".str_pad((string) ($secuencial + 1), 5, '0', STR_PAD_LEFT);
    }

    /** El PDF del respaldo: vive en el disco privado. */
    public function documento(Licencia $licencia): ?string
    {
        return $licencia->documento_ruta && Storage::disk('local')->exists($licencia->documento_ruta)
            ? $licencia->documento_ruta
            : null;
    }
}

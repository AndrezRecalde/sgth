<?php

namespace App\Services\Asistencia;

use App\Enums\CausaLicencia;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoPermiso;
use App\Enums\TipoAusenciaTitular;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Licencia;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Expediente\ClausulaDeLicencia;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\SubrogacionService;
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
 *   A un obrero, el contrato colectivo puede ampliarlo o darle una causa
 *   pactada, con su tope por año si la cláusula lo pone (fase 5.3).
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

    public function __construct(
        private AprobacionLicenciaSirha7Service $aprobacion,
        private CatalogoDeLicenciasService $catalogo,
    ) {}

    /**
     * @param array{estado?: ?string, causa?: ?string, causas?: ?list<string>, folio?: ?string, servidor_id?: ?int,
     *              unidad_administrativa_id?: ?int, fecha_desde?: ?string, fecha_hasta?: ?string, per_page?: ?int} $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = Licencia::query()->with([
            'servidor.unidadAdministrativa', 'aprobadoPor.servidor', 'creadoPor.servidor', 'clausulaDeLicencia.contratoColectivo',
        ]);

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
     * @param array{servidor_id: int, causa: string, alcance?: ?string, clausula_de_licencia_id?: ?int, fecha_inicio: string, fecha_fin: string, observacion?: ?string} $datos
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
        $clausulaId = filled($datos['clausula_de_licencia_id'] ?? null) ? (int) $datos['clausula_de_licencia_id'] : null;

        return DB::transaction(function () use ($datos, $archivo, $userId, $servidorId, $causa, $alcance, $clausulaId, $inicio, $fin, $dias) {
            // El mismo bloqueo que las vacaciones del servidor: una licencia y
            // unas vacaciones a la vez no pasan las dos el cruce. Y dos
            // licencias de la misma cláusula, su tope por año.
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("vacaciones_servidor_{$servidorId}")]);

            $this->rechazarSiSeCruza($servidorId, $inicio, $fin);

            ['clausula' => $clausula] = $this->catalogo->resolver($causa, $alcance, $clausulaId, $servidorId, $inicio);
            $clausula = $this->dentroDelTopeAnual($clausula, $causa, $alcance, $servidorId, $inicio, $dias);
            $this->validarTope($causa, $alcance, $clausula, $dias);

            $ruta = $archivo->store('asistencia/licencias/'.$inicio->year, 'local');

            if (! $ruta) {
                throw new ReglaNegocioException('No se pudo guardar el respaldo. Inténtelo de nuevo.');
            }

            return Licencia::create([
                'folio'            => $this->generarFolio(),
                'servidor_id'      => $servidorId,
                'causa'            => $causa->value,
                'alcance'          => $alcance,
                'clausula_de_licencia_id' => $clausula?->id,
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

            // La subrogación que esta licencia motivaba se cancela con ella
            // (fase 5.2).
            app(SubrogacionService::class)->cancelarPorAusencia(
                TipoAusenciaTitular::LICENCIA,
                $licencia->id,
                "Se anuló la licencia {$licencia->folio} del titular: {$motivo}",
            );

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

    /**
     * El tope por licencia: el de la cláusula del contrato colectivo, si la
     * respalda, o el de la ley.
     */
    private function validarTope(CausaLicencia $causa, ?string $alcance, ?ClausulaDeLicencia $clausula, int $dias): void
    {
        $tope = $clausula?->dias ?? $causa->tope($alcance);

        if ($tope !== null && $dias > $tope) {
            $nombre = $clausula?->esCausaNueva() ? $clausula->nombre : $causa->etiqueta();
            $base = $clausula
                ? ($clausula->esCausaNueva() ? '' : $causa->baseLegal().'; ').$clausula->baseLegal()
                : $causa->baseLegal();

            throw new ReglaNegocioException(
                "La licencia por {$nombre} admite hasta ".CatalogoDeLicenciasService::enDias($tope)
                    ." ({$base}), y las fechas suman {$dias}."
            );
        }
    }

    /**
     * El tope por año de la cláusula, si lo tiene: lo que el obrero ya tomó
     * por ella en ese año calendario, más esta licencia.
     *
     * Pasado el tope, la causa pactada no se registra. La que amplía una causa
     * de la ley vuelve a los días de la ley, que no tienen tope por año: el
     * contrato solo puede mejorarla (Código del Trabajo, Art. 4).
     */
    private function dentroDelTopeAnual(
        ?ClausulaDeLicencia $clausula, CausaLicencia $causa, ?string $alcance, int $servidorId, Carbon $inicio, int $dias,
    ): ?ClausulaDeLicencia {
        if ($clausula?->dias_por_anio === null) {
            return $clausula;
        }

        $tomados = (int) Licencia::where('servidor_id', $servidorId)
            ->where('clausula_de_licencia_id', $clausula->id)
            ->whereNull('anulado_en')
            ->whereYear('fecha_inicio', $inicio->year)
            ->sum('dias');

        if ($tomados + $dias <= $clausula->dias_por_anio) {
            return $clausula;
        }

        if ($clausula->esCausaNueva()) {
            throw new ReglaNegocioException(
                "La {$clausula->clausula} del contrato colectivo da hasta ".CatalogoDeLicenciasService::enDias($clausula->dias_por_anio)
                    ." al año por {$clausula->nombre}: en {$inicio->year} ya se ".($tomados === 1 ? 'tomó ' : 'tomaron ')
                    .CatalogoDeLicenciasService::enDias($tomados).', y con esta licencia serían '
                    .CatalogoDeLicenciasService::enDias($tomados + $dias).'.'
            );
        }

        return null;
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
                "Las fechas se cruzan con la licencia {$otra->folio} ({$otra->etiquetaDeLaCausa()}), del "
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

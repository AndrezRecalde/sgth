<?php

namespace App\Services\Asistencia;

use App\Contracts\Asistencia\PermisoServiceInterface;
use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Exceptions\ReglaNegocioException;
use App\Helpers\DiasHabilesHelper;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PermisoService implements PermisoServiceInterface
{
    use DiasHabilesHelper;

    /** Jornada completa, en minutos: 8 horas. */
    private const MINUTOS_JORNADA = 480;

    /** Tope diario de los permisos personales, en minutos. */
    private const MINUTOS_MAX_PERSONAL_DIA = 240;

    /**
     * Plazo para presentar el respaldo, en días hábiles, y también la
     * tolerancia hacia atrás al registrar un permiso planificable.
     */
    private const DIAS_HABILES_PLAZO = 3;

    /**
     * Estados en los que un permiso ocupa la jornada del servidor.
     *
     * Un permiso anulado o rechazado no estorba a otro en la misma franja, y
     * una falta injustificada tampoco: dejó de ser un permiso.
     */
    private const ESTADOS_QUE_OCUPAN = [
        EstadoPermiso::PENDIENTE,
        EstadoPermiso::ACTIVO,
        EstadoPermiso::VALIDADO_TRABAJO_SOCIAL,
    ];

    public function __construct(
        private PeriodoVacacionService $periodoService
    ) {}

    public function crear(array $datos, int $servidorId): PermisoServidor
    {
        return DB::transaction(function () use ($datos, $servidorId) {
            $servidor = Servidor::findOrFail($servidorId);
            $tipo = TipoPermiso::tryFrom($datos['tipo'])
                ?? throw new ReglaNegocioException('El tipo de permiso no es válido.');
            $observacion = $datos['observacion'] ?? null;
            $fecha = Carbon::parse($datos['fecha'])->startOfDay();

            $minutosInicio = $this->aMinutos($datos['hora_inicio']);
            $minutosFin    = $this->aMinutos($datos['hora_fin']);

            if ($minutosFin <= $minutosInicio) {
                throw new ReglaNegocioException(
                    'La hora de fin debe ser mayor a la hora de inicio.'
                );
            }

            $this->validarFecha($tipo, $fecha);
            $this->validarObservacion($tipo, $observacion);

            // A partir de aquí se lee la jornada del servidor en esa fecha, así
            // que se bloquean sus permisos de ese día: dos solicitudes a la vez
            // podrían pasar cada una su propia validación y sumar entre las dos
            // más horas de las que caben.
            $delDia = $this->permisosDelDia($servidorId, $fecha)
                ->lockForUpdate()
                ->get();

            $this->validarSolapamiento($delDia, $minutosInicio, $minutosFin);
            $this->validarTopeDiarioPersonal($tipo, $delDia, $minutosFin - $minutosInicio);
            $this->validarSaldoVacacional(
                $tipo, $servidor, $fecha, $minutosFin - $minutosInicio
            );

            $unidadId = $datos['unidad_administrativa_id']
                ?? $servidor->unidad_administrativa_id
                ?? null;

            return PermisoServidor::create([
                'servidor_id'              => $servidorId,
                'unidad_administrativa_id' => $unidadId,
                'jefe_id'                  => $datos['jefe_id'] ?? null,
                'creado_por'               => $datos['creado_por'] ?? null,
                'tipo'                     => $tipo->value,
                'fecha'                    => $fecha->toDateString(),
                'hora_inicio'              => $datos['hora_inicio'],
                'hora_fin'                 => $datos['hora_fin'],
                'observacion'              => $observacion,
                'estado'                   => EstadoPermiso::PENDIENTE->value,
                'vence_en'                 => $this->calcularVencimiento($fecha),
                'folio'                    => $this->siguienteFolio(),
            ]);
        });
    }

    public function confirmarRecepcion(
        string $folio,
        int $recepcionUserId
    ): PermisoServidor {
        // Todo dentro de una transacción y con la fila bloqueada: antes esto
        // guardaba el permiso y después descontaba las vacaciones, sin nada que
        // atara las dos cosas. Dos confirmaciones simultáneas del mismo folio
        // —o un fallo en medio— descontaban el saldo dos veces, o ninguna con
        // el permiso ya activo.
        return DB::transaction(function () use ($folio, $recepcionUserId) {
            $permiso = PermisoServidor::where('folio', $folio)
                ->lockForUpdate()
                ->firstOrFail();

            $this->exigirEstado($permiso, [EstadoPermiso::PENDIENTE],
                'Solo se pueden confirmar permisos en estado PENDIENTE.');

            $permiso->loadMissing('servidor');

            $dias = $this->diasVacacionalesQueConsume($permiso);

            if ($dias > 0) {
                $anio = Carbon::parse($permiso->fecha)->year;

                // El saldo se comprobó al crear el permiso, pero entre aquello
                // y esto pudo cerrarse el período. Callarse aquí es lo que
                // hacía que las horas se concedieran sin salir de ningún lado.
                $periodo = $this->periodoService->periodoAbierto(
                    $permiso->servidor_id, $anio
                );

                if (! $periodo) {
                    throw new ReglaNegocioException(
                        "No hay un período de vacaciones abierto en {$anio} para este servidor: " .
                        'el permiso personal no puede descontarse de ningún saldo.'
                    );
                }

                $this->periodoService->descontarDias(
                    $permiso->servidor_id, $dias, $anio
                );
            }

            $permiso->estado         = EstadoPermiso::ACTIVO->value;
            $permiso->confirmado_por = $recepcionUserId;
            $permiso->confirmado_en  = now();
            $permiso->save();

            return $permiso;
        });
    }

    public function validarTrabajoSocial(int $permisoId, int $tsUserId): PermisoServidor
    {
        $permiso = PermisoServidor::findOrFail($permisoId);

        if (! $this->esDeTrabajoSocial($permiso)) {
            throw new ReglaNegocioException(
                'La validación de Trabajo Social solo aplica para permisos por Enfermedad o Calamidad Doméstica.'
            );
        }

        $this->exigirEstado($permiso, [EstadoPermiso::ACTIVO],
            'El permiso debe estar ACTIVO para ser validado por Trabajo Social.');

        $permiso->estado          = EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value;
        $permiso->validado_ts_por = $tsUserId;
        $permiso->validado_ts_en  = now();
        $permiso->save();

        return $permiso;
    }

    /**
     * Recepción rechaza el documento físico.
     *
     * El estado existía en el enum desde el principio y nada lo asignaba: si
     * el papel llegaba adulterado, sin firma del jefe o con datos que no
     * cuadran, lo único que podía hacer Recepción era no confirmarlo y esperar
     * a que venciera solo. El rechazo lo deja dicho, con nombre y motivo, y no
     * descuenta nada porque el permiso nunca llegó a estar activo.
     */
    public function rechazar(int $permisoId, int $userId, string $motivo): PermisoServidor
    {
        return DB::transaction(function () use ($permisoId, $userId, $motivo) {
            $permiso = PermisoServidor::lockForUpdate()->findOrFail($permisoId);

            $this->exigirEstado($permiso, [EstadoPermiso::PENDIENTE],
                'Solo se puede rechazar un permiso en estado PENDIENTE.');

            $permiso->estado         = EstadoPermiso::RECHAZADO->value;
            $permiso->rechazado_por  = $userId;
            $permiso->rechazado_en   = now();
            $permiso->motivo_rechazo = $motivo;
            $permiso->save();

            return $permiso;
        });
    }

    /**
     * Deshace una confirmación y devuelve las horas descontadas.
     *
     * Confirmar era irreversible: `anular()` solo acepta permisos PENDIENTES,
     * así que un permiso confirmado por error se quedaba activo para siempre y
     * con el saldo vacacional ya descontado. El permiso vuelve a PENDIENTE
     * —sigue en plazo si aún no venció— en vez de anularse, porque lo que se
     * corrige es la recepción del documento, no el permiso en sí.
     */
    public function revertirConfirmacion(
        int $permisoId,
        int $userId,
        string $motivo
    ): PermisoServidor {
        return DB::transaction(function () use ($permisoId, $userId, $motivo) {
            $permiso = PermisoServidor::lockForUpdate()->findOrFail($permisoId);

            $this->exigirEstado(
                $permiso,
                [EstadoPermiso::ACTIVO, EstadoPermiso::VALIDADO_TRABAJO_SOCIAL],
                'Solo se puede revertir un permiso ya confirmado por Recepción.'
            );

            $dias = $this->diasVacacionalesQueConsume($permiso);

            if ($dias > 0) {
                $this->periodoService->devolverDias(
                    $permiso->servidor_id,
                    $dias,
                    Carbon::parse($permiso->fecha)->year
                );
            }

            $permiso->estado          = EstadoPermiso::PENDIENTE->value;
            $permiso->confirmado_por  = null;
            $permiso->confirmado_en   = null;
            $permiso->validado_ts_por = null;
            $permiso->validado_ts_en  = null;
            $permiso->motivo_rechazo  = $motivo;
            $permiso->save();

            return $permiso;
        });
    }

    // ── Reglas ───────────────────────────────────────────────────────

    /**
     * Cuándo puede haber ocurrido un permiso.
     *
     * Se parte en dos porque los dos grupos son cosas distintas. Un permiso
     * PERSONAL u OFICIAL se pide antes de ausentarse: se imprime, se firma y se
     * lleva a Recepción, así que su fecha es hoy o más adelante. Se admite una
     * tolerancia de tres días hábiles hacia atrás —el mismo plazo que rige la
     * confirmación— para digitalizar el que llegó tarde en papel.
     *
     * ENFERMEDAD y CALAMIDAD son lo contrario: nadie sabe que se va a enfermar,
     * y las 72 horas del plazo existen precisamente para justificar después. Se
     * registran hacia atrás sin límite, pero nunca a futuro.
     */
    private function validarFecha(TipoPermiso $tipo, Carbon $fecha): void
    {
        $hoy = Carbon::today();

        if ($tipo === TipoPermiso::ENFERMEDAD || $tipo === TipoPermiso::CALAMIDAD) {
            if ($fecha->greaterThan($hoy)) {
                throw new ReglaNegocioException(
                    'Un permiso por enfermedad o calamidad doméstica no puede registrarse con fecha futura.'
                );
            }

            return;
        }

        $limite = $this->restarDiasHabiles($hoy, self::DIAS_HABILES_PLAZO);

        if ($fecha->lessThan($limite)) {
            throw new ReglaNegocioException(
                'Un permiso ' . mb_strtolower($tipo->name) . ' no puede registrarse con más de ' .
                self::DIAS_HABILES_PLAZO . ' días hábiles de atraso. ' .
                'La fecha más antigua admitida es ' . $limite->format('d/m/Y') . '.'
            );
        }
    }

    private function validarObservacion(TipoPermiso $tipo, ?string $observacion): void
    {
        if ($tipo === TipoPermiso::OFICIAL && trim((string) $observacion) === '') {
            throw new ReglaNegocioException(
                'La observación es OBLIGATORIA para los permisos de tipo OFICIAL.'
            );
        }
    }

    /**
     * Nadie puede estar ausente dos veces a la vez.
     *
     * No había ninguna comprobación: se podían apilar cuatro permisos de cuatro
     * horas el mismo día y en la misma franja, cada uno válido por su cuenta.
     */
    private function validarSolapamiento(
        \Illuminate\Support\Collection $delDia,
        int $inicio,
        int $fin
    ): void {
        $choque = $delDia->first(function (PermisoServidor $p) use ($inicio, $fin) {
            $pInicio = $this->aMinutos($p->getRawOriginal('hora_inicio'));
            $pFin    = $this->aMinutos($p->getRawOriginal('hora_fin'));

            return $inicio < $pFin && $fin > $pInicio;
        });

        if ($choque) {
            throw new ReglaNegocioException(
                'El servidor ya tiene el permiso ' . $choque->folio . ' de ' .
                substr((string) $choque->getRawOriginal('hora_inicio'), 0, 5) . ' a ' .
                substr((string) $choque->getRawOriginal('hora_fin'), 0, 5) .
                ' ese mismo día. Los horarios no pueden solaparse.'
            );
        }
    }

    /**
     * El máximo de cuatro horas es POR DÍA, no por solicitud.
     *
     * El comentario del código ya lo decía y la comprobación miraba solo el
     * permiso que se estaba creando, así que dos de cuatro horas en horarios
     * distintos del mismo día pasaban sin problema.
     */
    private function validarTopeDiarioPersonal(
        TipoPermiso $tipo,
        \Illuminate\Support\Collection $delDia,
        int $minutos
    ): void {
        if ($tipo !== TipoPermiso::PERSONAL) {
            return;
        }

        if ($minutos > self::MINUTOS_MAX_PERSONAL_DIA) {
            throw new ReglaNegocioException(
                'Los permisos de tipo PERSONAL no pueden exceder las 4 horas por día.'
            );
        }

        $yaUsados = $delDia
            ->filter(fn (PermisoServidor $p) => $this->tipoDe($p) === TipoPermiso::PERSONAL->value)
            ->sum(fn (PermisoServidor $p) =>
                $this->aMinutos($p->getRawOriginal('hora_fin'))
                - $this->aMinutos($p->getRawOriginal('hora_inicio'))
            );

        if ($yaUsados + $minutos > self::MINUTOS_MAX_PERSONAL_DIA) {
            $restantes = max(0, self::MINUTOS_MAX_PERSONAL_DIA - $yaUsados);

            throw new ReglaNegocioException(
                'Los permisos de tipo PERSONAL no pueden exceder las 4 horas por día. ' .
                'Ese día ya tiene ' . $this->enHoras($yaUsados) . ' concedidas y solo le quedan ' .
                $this->enHoras($restantes) . '.'
            );
        }
    }

    /**
     * El saldo de vacaciones es el tope acumulado del permiso personal.
     *
     * El descuento ya existía —al confirmar—, pero nadie comprobaba que hubiera
     * de dónde descontar: sin período abierto se saltaba en silencio, y con
     * saldo insuficiente el período quedaba en cero y el resto se perdía. Si el
     * permiso se paga con vacaciones, no puede concederse lo que no hay.
     */
    private function validarSaldoVacacional(
        TipoPermiso $tipo,
        Servidor $servidor,
        Carbon $fecha,
        int $minutos
    ): void {
        if ($tipo !== TipoPermiso::PERSONAL || ! $this->descuentaVacaciones($servidor)) {
            return;
        }

        $dias    = $this->aDias($minutos);
        $periodo = $this->periodoService->periodoAbierto($servidor->id, $fecha->year);

        if (! $periodo) {
            throw new ReglaNegocioException(
                "El servidor no tiene un período de vacaciones abierto en {$fecha->year}, " .
                'y un permiso personal se descuenta de ese saldo.'
            );
        }

        if ((float) $periodo->dias_saldo < $dias) {
            throw new ReglaNegocioException(
                'Saldo de vacaciones insuficiente: el permiso descuenta ' .
                number_format($dias, 2) . ' días y solo quedan ' .
                number_format((float) $periodo->dias_saldo, 2) . '.'
            );
        }
    }

    // ── Folio y vencimiento ──────────────────────────────────────────

    /**
     * Folio único del año, sin carreras.
     *
     * Antes salía de un `count()` de la tabla. Dos solicitudes simultáneas leían
     * el mismo número y la segunda moría contra el índice único; y como el
     * `count()` no ve los borrados en blando, bastaba un borrado para que la
     * secuencia retrocediera y volviera a chocar.
     *
     * El candado de aviso serializa solo la generación de folios de este año y
     * se suelta al cerrar la transacción. Es de PostgreSQL, que es el único
     * motor del proyecto.
     */
    private function siguienteFolio(): string
    {
        $anio = (int) now()->format('Y');

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["permiso_folio_{$anio}"]);

        // El sufijo va rellenado a cinco cifras, así que el orden alfabético y
        // el numérico coinciden y `max()` basta. `withTrashed()` para que un
        // permiso borrado no libere su número.
        $ultimo = PermisoServidor::withTrashed()
            ->where('folio', 'like', "PER-{$anio}-%")
            ->max('folio');

        $secuencial = $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1;

        return sprintf('PER-%d-%05d', $anio, $secuencial);
    }

    /**
     * Las 72 horas laborables, contadas en días hábiles de verdad.
     *
     * El cálculo saltaba solo los fines de semana, así que un permiso del
     * viernes de Carnaval vencía mientras la institución estaba cerrada. Reusa
     * el mismo helper con el que Viáticos y Disciplinario cuentan sus plazos,
     * que sí consulta el calendario de feriados.
     */
    private function calcularVencimiento(Carbon $fecha): Carbon
    {
        return $this->calcularDiasHabiles($fecha, self::DIAS_HABILES_PLAZO)
            ->startOfDay();
    }

    private function restarDiasHabiles(Carbon $desde, int $dias): Carbon
    {
        $fecha = $desde->copy();
        $restados = 0;

        while ($restados < $dias) {
            $fecha->subDay();

            if ($fecha->isWeekend()) {
                continue;
            }

            if (\App\Models\Asistencia\FeriadoInstitucional::esFeriado($fecha)->exists()) {
                continue;
            }

            $restados++;
        }

        return $fecha->startOfDay();
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    private function permisosDelDia(int $servidorId, Carbon $fecha): Builder
    {
        return PermisoServidor::query()
            ->where('servidor_id', $servidorId)
            ->whereDate('fecha', $fecha->toDateString())
            ->whereIn('estado', array_map(
                fn (EstadoPermiso $e) => $e->value,
                self::ESTADOS_QUE_OCUPAN
            ));
    }

    /**
     * Días de vacaciones que consume un permiso ya guardado, o 0 si no consume.
     *
     * Solo los permisos PERSONAL de servidores LOSEP descuentan: el Código del
     * Trabajo se rige por su contrato colectivo.
     */
    private function diasVacacionalesQueConsume(PermisoServidor $permiso): float
    {
        if ($this->tipoDe($permiso) !== TipoPermiso::PERSONAL->value) {
            return 0.0;
        }

        $permiso->loadMissing('servidor');

        if (! $permiso->servidor || ! $this->descuentaVacaciones($permiso->servidor)) {
            return 0.0;
        }

        $minutos = $this->aMinutos($permiso->getRawOriginal('hora_fin'))
            - $this->aMinutos($permiso->getRawOriginal('hora_inicio'));

        return $minutos > 0 ? $this->aDias($minutos) : 0.0;
    }

    private function descuentaVacaciones(Servidor $servidor): bool
    {
        $regimen = $servidor->regimen_laboral instanceof \App\Enums\RegimenLaboral
            ? $servidor->regimen_laboral->value
            : (string) ($servidor->regimen_laboral ?? 'losep');

        return $regimen === \App\Enums\RegimenLaboral::LOSEP->value;
    }

    private function exigirEstado(PermisoServidor $permiso, array $permitidos, string $mensaje): void
    {
        $actual = $permiso->estado instanceof EstadoPermiso
            ? $permiso->estado->value
            : (string) $permiso->estado;

        $valores = array_map(fn (EstadoPermiso $e) => $e->value, $permitidos);

        if (! in_array($actual, $valores, true)) {
            throw new ReglaNegocioException("{$mensaje} Estado actual: {$actual}");
        }
    }

    private function esDeTrabajoSocial(PermisoServidor $permiso): bool
    {
        return in_array($this->tipoDe($permiso), [
            TipoPermiso::ENFERMEDAD->value,
            TipoPermiso::CALAMIDAD->value,
        ], true);
    }

    private function tipoDe(PermisoServidor $permiso): string
    {
        return $permiso->tipo instanceof TipoPermiso
            ? $permiso->tipo->value
            : (string) $permiso->tipo;
    }

    /** `"08:30:00"` o `"08:30"` → 510. */
    private function aMinutos(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)));

        return $h * 60 + $m;
    }

    private function aDias(int $minutos): float
    {
        return round($minutos / self::MINUTOS_JORNADA, 4);
    }

    private function enHoras(int $minutos): string
    {
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        if ($horas === 0) {
            return "{$resto} min";
        }

        return $resto === 0 ? "{$horas} h" : "{$horas} h {$resto} min";
    }
}

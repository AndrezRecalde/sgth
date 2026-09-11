<?php

namespace App\Services\Asistencia;

use App\Contracts\Asistencia\VacacionMotorInterface;
use App\Contracts\Asistencia\VacacionServiceInterface;
use App\Enums\EstadoPermiso;
use App\Enums\MotivoVacacion;
use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\Motores\VacacionCodigoTrabajoService;
use App\Services\Asistencia\Motores\VacacionLosepService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VacacionService implements VacacionServiceInterface
{
    /**
     * Motor de cálculo según la jurisprudencia aplicable al servidor.
     *
     * Se decide con un `match` sobre los tres regímenes y no descartando el
     * Código del Trabajo: con la forma anterior, el régimen de servicios
     * profesionales —agregado el 2026-08-29— caía por omisión en el motor
     * LOSEP y se le calculaban vacaciones que un contrato civil no genera.
     * Un cuarto régimen tendría que decidir aquí en vez de heredar una rama.
     */
    public function obtenerMotor(Servidor $servidor): VacacionMotorInterface
    {
        $regimen = $servidor->regimen_laboral instanceof RegimenLaboral
            ? $servidor->regimen_laboral
            : RegimenLaboral::tryFrom((string) ($servidor->regimen_laboral ?? 'losep'));

        return match ($regimen) {
            RegimenLaboral::CODIGO_TRABAJO => new VacacionCodigoTrabajoService(),
            RegimenLaboral::LOSEP, null    => new VacacionLosepService(),
            RegimenLaboral::SERVICIOS_PROFESIONALES => throw new ReglaNegocioException(
                'Un contrato de servicios profesionales no genera vacaciones: '
                .'se pacta un entregable, no una jornada.'
            ),
        };
    }

    /**
     * Días disponibles: la suma de los períodos abiertos, y nada más.
     *
     * Había un cálculo legacy de respaldo —días del motor por años de
     * antigüedad, menos lo gozado— que entraba cuando esa suma daba cero. Pero
     * cero no significa «no hay períodos»: es también el saldo de quien ya
     * gozó todo. A esa persona el respaldo le devolvía el saldo de su carrera
     * entera —160 días para ocho años LOSEP— y le dejaba pedir vacaciones que
     * no tenía. El mismo número llegaba al KPI del dashboard y al autoservicio.
     *
     * Tampoco valía como respaldo para quien no tiene períodos: suponía que
     * nunca gozó nada antes de que existiera el sistema. El saldo sale de los
     * períodos; quien no tiene uno abierto no tiene saldo, y `solicitar()` se
     * lo explica en vez de decirle que le faltan días.
     */
    public function calcularSaldoActual(int $servidorId): float
    {
        $servidor = Servidor::findOrFail($servidorId);

        if (! $this->generaVacaciones($servidor)) {
            return 0.0;
        }

        return app(PeriodoVacacionService::class)->saldoTotal($servidorId);
    }

    /**
     * ¿El régimen de este servidor genera vacaciones?
     *
     * Se pregunta por la capacidad —`RegimenLaboral::generaVacaciones()`— en
     * vez de comparar cadenas en cada sitio.
     */
    private function generaVacaciones(Servidor $servidor): bool
    {
        $regimen = $servidor->regimen_laboral instanceof RegimenLaboral
            ? $servidor->regimen_laboral
            : RegimenLaboral::tryFrom((string) ($servidor->regimen_laboral ?? 'losep'));

        return $regimen?->generaVacaciones() ?? true;
    }

    /**
     * Las reglas de negocio se lanzan como `ReglaNegocioException`. Antes eran
     * `\Exception` genéricas: el manejador las convertía en un 500 «Error
     * interno del servidor.» y el motivo —saldo insuficiente, fechas al revés—
     * no le llegaba a nadie.
     */
    public function solicitar(array $datos, int $servidorId): Vacacion
    {
        return DB::transaction(function () use ($datos, $servidorId) {
            // Serializa las altas de un mismo servidor: sin esto, dos
            // solicitudes simultáneas con las mismas fechas pasarían las dos el
            // control de cruce de más abajo.
            DB::select('SELECT pg_advisory_xact_lock(?)', [
                crc32("vacaciones_servidor_{$servidorId}"),
            ]);

            $servidor = Servidor::findOrFail($servidorId);

            // Se corta aquí y no en el saldo: el mensaje tiene que explicar el
            // motivo —el régimen— y no aparecer como «no tiene días».
            if (! $this->generaVacaciones($servidor)) {
                throw new ReglaNegocioException(
                    'El régimen de este servidor no genera vacaciones.'
                );
            }

            $motor = $this->obtenerMotor($servidor);

            $fechaInicio = Carbon::parse($datos['fecha_inicio']);
            $fechaFin    = Carbon::parse($datos['fecha_fin']);

            if ($fechaFin->lessThan($fechaInicio)) {
                throw new ReglaNegocioException(
                    'La fecha de fin no puede ser menor a la fecha de inicio.'
                );
            }

            $diasADescontar = $motor->calcularDiasDescuento($fechaInicio, $fechaFin);

            if ($diasADescontar <= 0) {
                throw new ReglaNegocioException(
                    'Las fechas seleccionadas no representan días laborables descontables.'
                );
            }

            $this->rechazarSiSeCruza($servidorId, $fechaInicio, $fechaFin);
            $this->rechazarSiHayPermisos($servidorId, $fechaInicio, $fechaFin);

            $reemplazoId = isset($datos['persona_reemplaza_id'])
                ? (int) $datos['persona_reemplaza_id']
                : null;

            if ($reemplazoId) {
                $this->validarReemplazo($reemplazoId, $servidorId, $fechaInicio, $fechaFin);
            }

            $motivo = MotivoVacacion::tryFrom($datos['motivo'] ?? '');

            // Solo verificar saldo si el motivo descuenta vacaciones
            if ($motivo?->descuentaVacaciones()) {
                $periodos = app(PeriodoVacacionService::class);

                // Saldo cero puede ser «gozó todo» o «no tiene de dónde
                // descontar». Son dos problemas distintos y cada uno se
                // resuelve en un sitio distinto.
                if (! $periodos->tienePeriodoAbierto($servidorId)) {
                    throw new ReglaNegocioException(
                        'El servidor no tiene un período de vacaciones abierto del que descontar. '
                        .'Genérelo en «Períodos de vacaciones» antes de registrar la solicitud.'
                    );
                }

                // El mismo saldo que usará la aprobación: los períodos hasta el
                // año de la vacación, no los que alguien generó por adelantado.
                $saldoActual = $periodos->saldoHasta($servidorId, $fechaInicio->year);
                if ($diasADescontar > $saldoActual) {
                    throw new ReglaNegocioException(
                        "Saldo insuficiente: la solicitud descuenta {$diasADescontar} días ".
                        'y el saldo disponible es de '.number_format($saldoActual, 2).' días.'
                    );
                }
            }

            // Días calendario en los dos regímenes: la LOSEP (art. 29) y el
            // Código del Trabajo (art. 69), confirmado con Talento Humano. La
            // LOSEP se guardaba como «hábiles»; las solicitudes antiguas
            // conservan su tipo, y su PDF sigue diciendo cómo se contaron.
            $tipoDias = 'calendario';

            $folio = $this->generarFolio();

            // La persona que reemplaza y la observación llegaban validadas y se
            // descartaban aquí: el PDF salía siempre con «—» en los dos.
            $vacacion = Vacacion::create([
                'servidor_id'              => $servidorId,
                'unidad_administrativa_id' => $datos['unidad_administrativa_id'] ?? $servidor->unidad_administrativa_id ?? null,
                'jefe_id'                  => $datos['jefe_id'] ?? null,
                'persona_reemplaza_id'     => $reemplazoId,
                'motivo'                   => $datos['motivo'] ?? null,
                'fecha_inicio'             => $fechaInicio,
                'fecha_fin'        => $fechaFin,
                'fecha_retorno'    => $datos['fecha_retorno'] ?? null,
                'fecha_emision'    => $datos['fecha_emision'] ?? now()->toDateString(),
                'dias_solicitados' => $diasADescontar,
                'tipo_dias'        => $tipoDias,
                'observacion'      => filled($datos['observacion'] ?? null) ? trim($datos['observacion']) : null,
                'estado'           => 'pendiente',
                'creado_por'       => $datos['creado_por'] ?? null,
                'folio'            => $folio,
                'codigo_qr'        => Vacacion::urlVerificacion($folio),
            ]);

            return $vacacion->fresh(['servidor', 'jefe', 'creadoPor', 'personaReemplaza']);
        });
    }

    /**
     * Aprueba o rechaza una solicitud. Solo desde PENDIENTE, y una sola vez.
     *
     * Antes esto vivía en el controlador y aceptaba cualquier cambio de estado.
     * La única defensa contra el doble descuento era «el estado anterior no era
     * aprobada», que no mira más atrás: aprobar → rechazar → aprobar descontaba
     * dos veces, y rechazar una ya aprobada no devolvía nada. Sin transacción ni
     * bloqueo, además, un doble clic podía descontar dos veces directamente.
     *
     * Deshacer una aprobada —devolviendo sus días— no es «rechazarla»: es una
     * anulación, con su propio motivo y su propio registro. Ver `anular()`.
     */
    public function resolver(int $vacacionId, string $nuevoEstado, User $resolutor): Vacacion
    {
        return DB::transaction(function () use ($vacacionId, $nuevoEstado, $resolutor) {
            // Bloquea la fila: la segunda de dos peticiones simultáneas espera
            // aquí y, cuando entra, ya la encuentra resuelta.
            $vacacion = Vacacion::lockForUpdate()->findOrFail($vacacionId);

            $this->rechazarSiEsPropia($resolutor, $vacacion, 'aprobar ni rechazar');

            $estadoActual = (string) $vacacion->estado;

            if ($estadoActual !== 'pendiente') {
                throw new ReglaNegocioException(sprintf(
                    'La solicitud %s ya fue resuelta como %s: solo se aprueba o rechaza una solicitud pendiente.',
                    $vacacion->folio ?? "#{$vacacion->id}",
                    $estadoActual
                ));
            }

            if ($nuevoEstado === 'aprobada') {
                // Entre la solicitud y la aprobación alguien pudo registrar un
                // permiso en esas fechas: se vuelve a mirar, igual que el saldo.
                $this->rechazarSiHayPermisos(
                    (int) $vacacion->servidor_id,
                    Carbon::parse($vacacion->fecha_inicio),
                    Carbon::parse($vacacion->fecha_fin)
                );

                if ($this->descuenta($vacacion)) {
                    // Reparte entre los períodos abiertos, del más antiguo al
                    // más nuevo, y comprueba el saldo con esos períodos
                    // bloqueados: una pendiente no reserva días, y dos que
                    // pasaron el control por separado pueden juntas superarlo.
                    app(PeriodoVacacionService::class)->consumirParaVacacion(
                        $vacacion,
                        (float) $vacacion->dias_solicitados,
                        Carbon::parse($vacacion->fecha_inicio)->year
                    );
                }

                $vacacion->aprobado_por = $resolutor->id;
            }

            $vacacion->estado = $nuevoEstado;
            $vacacion->save();

            return $vacacion->fresh(['servidor', 'jefe', 'creadoPor']);
        });
    }

    /**
     * Anula una solicitud pendiente, o una aprobada que todavía no comenzó.
     *
     * Hasta ahora lo único que había era «rechazar», y solo desde pendiente.
     * Una aprobación hecha por error —o unas vacaciones que la unidad pide
     * postergar— no tenía vuelta atrás: los días quedaban descontados.
     *
     * - Una pendiente no descontó nada: se anula y ya.
     * - Una aprobada devuelve sus días a los mismos períodos de donde salieron.
     * - Una que ya comenzó no se anula: devolvería días que se están gozando.
     *   Interrumpir unas vacaciones en curso es otra cosa, con días a medias.
     *
     * Queda quién la anuló, cuándo y por qué, en la propia solicitud.
     *
     * @return array{vacacion: Vacacion, dias_devueltos: float}
     */
    public function anular(int $vacacionId, string $motivo, User $usuario): array
    {
        return DB::transaction(function () use ($vacacionId, $motivo, $usuario) {
            $vacacion = Vacacion::lockForUpdate()->findOrFail($vacacionId);

            $this->rechazarSiEsPropia($usuario, $vacacion, 'anular');

            $estado = (string) $vacacion->estado;
            $folio  = $vacacion->folio ?? "#{$vacacion->id}";

            if (! in_array($estado, ['pendiente', 'aprobada'], true)) {
                throw new ReglaNegocioException(
                    "La solicitud {$folio} está {$estado}: solo se anula una solicitud pendiente o aprobada."
                );
            }

            $inicio = Carbon::parse($vacacion->fecha_inicio)->startOfDay();

            if ($estado === 'aprobada' && $inicio->lessThan(Carbon::today())) {
                throw new ReglaNegocioException(
                    "La solicitud {$folio} comenzó el {$inicio->format('d/m/Y')}: anularla devolvería días "
                    .'que ya se están gozando. Una vacación en curso no se interrumpe anulándola.'
                );
            }

            $devueltos = 0.0;

            if ($estado === 'aprobada' && $this->descuenta($vacacion)) {
                $devueltos = app(PeriodoVacacionService::class)->devolverDeVacacion($vacacion);
            }

            $vacacion->estado           = 'anulada';
            $vacacion->anulado_por      = $usuario->id;
            $vacacion->anulado_en       = now();
            $vacacion->motivo_anulacion = $motivo;
            $vacacion->save();

            return [
                'vacacion'       => $vacacion->fresh(['servidor', 'jefe', 'creadoPor']),
                'dias_devueltos' => $devueltos,
            ];
        });
    }

    /**
     * Pasa a GOZADA las aprobadas que terminaron antes de la fecha de corte.
     *
     * El estado existía desde la primera migración y nada lo asignaba. Una
     * vacación que termina hoy sigue aprobada hasta mañana: el último día
     * todavía se está gozando.
     *
     * @return array{marcadas: int, fecha: string}
     */
    public function marcarGozadas(?string $fecha = null): array
    {
        $corte = $fecha ? Carbon::parse($fecha)->startOfDay() : Carbon::today();

        $marcadas = Vacacion::where('estado', 'aprobada')
            ->whereDate('fecha_fin', '<', $corte->toDateString())
            ->update(['estado' => 'gozada']);

        return ['marcadas' => $marcadas, 'fecha' => $corte->toDateString()];
    }

    /**
     * Nadie resuelve ni anula su propia solicitud. Va en el servicio y no en la
     * policy: el Gate::before de admin-ti se saltaría la policy entera.
     */
    private function rechazarSiEsPropia(User $usuario, Vacacion $vacacion, string $accion): void
    {
        if (
            $usuario->servidor_id !== null
            && (int) $usuario->servidor_id === (int) $vacacion->servidor_id
        ) {
            throw new ReglaNegocioException(
                "No puede {$accion} su propia solicitud de vacaciones."
            );
        }
    }

    private function descuenta(Vacacion $vacacion): bool
    {
        $motivo = $vacacion->motivo instanceof MotivoVacacion
            ? $vacacion->motivo
            : MotivoVacacion::tryFrom((string) $vacacion->motivo);

        return (bool) $motivo?->descuentaVacaciones();
    }

    /**
     * Una persona no puede estar dos veces de vacaciones —o de licencia— el
     * mismo día. Cuenta todo lo que sigue vigente; lo rechazado y lo anulado no
     * ocupa fechas.
     */
    private function rechazarSiSeCruza(int $servidorId, Carbon $inicio, Carbon $fin): void
    {
        $cruce = $this->cruceCon($servidorId, $inicio, $fin);

        if ($cruce) {
            throw new ReglaNegocioException(sprintf(
                'Las fechas se cruzan con la solicitud %s (%s), del %s al %s.',
                $cruce->folio ?? "#{$cruce->id}",
                $cruce->estado,
                $cruce->fecha_inicio->format('d/m/Y'),
                $cruce->fecha_fin->format('d/m/Y')
            ));
        }
    }

    /**
     * Unas vacaciones no pueden caer sobre un día que ya tiene un permiso.
     *
     * El permiso ya no se registra sobre unas vacaciones, pero al revés nadie
     * miraba: un permiso personal confirmado descuenta sus horas del saldo, y
     * unas vacaciones aprobadas encima volvían a descontar ese mismo día. Con
     * los otros tipos no hay doble cobro, pero el día queda a la vez como
     * vacación y como enfermedad o comisión.
     *
     * Bloquean los permisos vivos, de cualquier tipo. Los anulados, rechazados
     * y las faltas injustificadas no ocupan el día.
     */
    private function rechazarSiHayPermisos(int $servidorId, Carbon $inicio, Carbon $fin): void
    {
        $permiso = PermisoServidor::where('servidor_id', $servidorId)
            ->whereIn('estado', [
                EstadoPermiso::PENDIENTE->value,
                EstadoPermiso::ACTIVO->value,
                EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value,
            ])
            ->whereDate('fecha', '>=', $inicio->toDateString())
            ->whereDate('fecha', '<=', $fin->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->first();

        if ($permiso) {
            throw new ReglaNegocioException(sprintf(
                'Las fechas incluyen el permiso %s (%s, %s) del %s, de %s a %s: '.
                'ese día ya tiene una ausencia registrada. Anule el permiso o elija otras fechas.',
                $permiso->folio ?? "#{$permiso->id}",
                $permiso->tipo?->value ?? $permiso->tipo,
                $permiso->estado?->value ?? $permiso->estado,
                $permiso->fecha->format('d/m/Y'),
                substr((string) $permiso->hora_inicio, 0, 5),
                substr((string) $permiso->hora_fin, 0, 5)
            ));
        }
    }

    /**
     * Quien reemplaza tiene que poder hacerlo: ser otra persona, estar activa
     * y no estar ella misma fuera en esas fechas.
     *
     * Hasta ahora el campo se validaba contra la tabla —que el servidor
     * existiera— y después se descartaba sin guardarlo, así que nada de esto
     * importaba. Guardado, un reemplazo que también está de vacaciones deja la
     * unidad sin nadie aunque el papel diga lo contrario.
     */
    private function validarReemplazo(int $reemplazoId, int $servidorId, Carbon $inicio, Carbon $fin): void
    {
        if ($reemplazoId === $servidorId) {
            throw new ReglaNegocioException(
                'La persona que reemplaza no puede ser el mismo servidor que sale de vacaciones.'
            );
        }

        $reemplazo = Servidor::findOrFail($reemplazoId);
        $nombre    = trim("{$reemplazo->apellido} {$reemplazo->nombre}");

        if (! $reemplazo->estado) {
            throw new ReglaNegocioException(
                "{$nombre} no está activo en la institución: no puede reemplazar a nadie."
            );
        }

        $cruce = $this->cruceCon($reemplazoId, $inicio, $fin);

        if ($cruce) {
            throw new ReglaNegocioException(sprintf(
                '%s tiene la solicitud %s del %s al %s: no puede reemplazar en esas fechas.',
                $nombre,
                $cruce->folio ?? "#{$cruce->id}",
                $cruce->fecha_inicio->format('d/m/Y'),
                $cruce->fecha_fin->format('d/m/Y')
            ));
        }
    }

    /**
     * La primera solicitud vigente de un servidor que se cruza con las fechas.
     */
    private function cruceCon(int $servidorId, Carbon $inicio, Carbon $fin): ?Vacacion
    {
        return Vacacion::where('servidor_id', $servidorId)
            ->whereIn('estado', ['pendiente', 'aprobada', 'gozada'])
            ->whereDate('fecha_inicio', '<=', $fin->toDateString())
            ->whereDate('fecha_fin', '>=', $inicio->toDateString())
            ->orderBy('fecha_inicio')
            ->first();
    }

    /**
     * El folio sale del mayor ya emitido, no de contar filas.
     *
     * Contar fallaba de dos maneras. `count()` no ve las borradas en blando,
     * así que tras borrar una solicitud el siguiente folio repetía uno ya
     * emitido y el índice único lo rechazaba —el borrado en blando no libera el
     * valor—. Y dos altas simultáneas leían el mismo conteo.
     *
     * Mismo arreglo que TUR-, ADQ-, MED- y ENF-. El bloqueo de aviso serializa
     * leer el máximo y escribir el folio, y lo suelta el cierre de la
     * transacción de `solicitar()`.
     */
    private function generarFolio(): string
    {
        $anio = now()->year;

        DB::select('SELECT pg_advisory_xact_lock(?)', [
            crc32("vacacion_folio_{$anio}"),
        ]);

        $ultimoFolio = Vacacion::withTrashed()
            ->where('folio', 'like', "VAC-{$anio}-%")
            ->max('folio');

        $ultimoSecuencial = $ultimoFolio
            ? (int) substr($ultimoFolio, strlen("VAC-{$anio}-"))
            : 0;

        $secuencial = str_pad(
            (string) ($ultimoSecuencial + 1), 5, '0', STR_PAD_LEFT
        );

        return "VAC-{$anio}-{$secuencial}";
    }
}

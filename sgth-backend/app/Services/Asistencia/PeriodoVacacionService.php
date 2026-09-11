<?php
namespace App\Services\Asistencia;

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\Vacacion;
use App\Models\Asistencia\VacacionDescuento;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PeriodoVacacionService
{
    /**
     * Calcula los días generados según régimen y antigüedad.
     */
    public function calcularDiasGenerados(
        string $regimen,
        int $aniosAntiguedad
    ): float {
        // Un contrato de servicios profesionales es civil: se pacta un
        // entregable, no una jornada, así que no genera vacaciones. Sin este
        // caso caía en la fórmula del Código del Trabajo y le generaba días.
        if ($regimen === RegimenLaboral::SERVICIOS_PROFESIONALES->value) {
            return 0.0;
        }

        if ($regimen === 'losep') {
            return match(true) {
                $aniosAntiguedad >= 16 => 30.0,
                $aniosAntiguedad >= 11 => 25.0,
                $aniosAntiguedad >= 6  => 20.0,
                default                => 15.0,
            };
        }

        // Código del Trabajo: 15 + 1 por cada año adicional
        return min(15.0 + max(0, $aniosAntiguedad - 1), 30.0);
    }

    /**
     * Calcula la antigüedad según régimen:
     * LOSEP → desde fecha_ingreso_sector_publico
     * CT    → desde fecha_ingreso_institucion
     */
    public function calcularAntiguedad(
        Servidor $servidor,
        string $regimen,
        int $anio
    ): int {
        $fechaRef = $regimen === 'losep'
            ? $servidor->fecha_ingreso_sector_publico
            : $servidor->fecha_ingreso_institucion;

        if (!$fechaRef) return 0;

        return (int) Carbon::parse($fechaRef)
            ->diffInYears(Carbon::create($anio, 12, 31));
    }

    /**
     * Genera o actualiza el período de un servidor para un año.
     */
    public function generarPeriodo(
        Servidor $servidor,
        int $anio,
        bool $forzar = false
    ): PeriodoVacacion {
        $existente = PeriodoVacacion::where('servidor_id', $servidor->id)
            ->where('anio', $anio)
            ->first();

        /**
         * A quien no genera vacaciones no se le abre un período.
         *
         * Antes se le creaba uno con cero días. Parecía inofensivo, pero deja a
         * un contratado civil dentro de la pantalla de vacaciones, contándose
         * entre los períodos de la plantilla y con un saldo que discutir. No
         * tiene jornada ni relación de dependencia: no es que le toquen cero
         * días, es que no le corresponde el período.
         *
         * Se lanza en vez de devolver algo vacío para que el endpoint conteste
         * el motivo. La generación masiva no llega aquí: filtra antes.
         *
         * Si el período YA existe se sigue de largo: es alguien que estuvo bajo
         * otro régimen y cuyo período hay que recalcular a cero conservando lo
         * que gozó. Eso ocurrió y no se borra.
         */
        if (! $existente && ! $this->generaVacaciones($servidor)) {
            throw new ReglaNegocioException(
                'El régimen de este servidor no genera vacaciones, '
                .'así que no le corresponde un período.'
            );
        }

        /**
         * Un período cerrado no se recalcula por rutina.
         *
         * Su saldo ya se certificó: se comunicó al servidor, se arrastró al año
         * siguiente y puede haberse liquidado. «Generar todos» es una operación
         * masiva y periódica, y corregir de paso la antigüedad de alguien le
         * cambiaría en silencio un saldo que ya constaba como suyo.
         *
         * Corregir un año cerrado sigue siendo posible, pero como acto
         * deliberado sobre ese servidor y ese año: eso es `$forzar`, que además
         * deja registro en la bitácora (ver `registrarRecalculoForzado`).
         */
        if ($existente && $existente->estado !== 'abierto' && ! $forzar) {
            return $existente;
        }

        $antes = $existente
            ? $existente->only(['dias_generados', 'dias_utilizados', 'dias_saldo', 'anios_antiguedad', 'regimen'])
            : null;

        [
            'regimen'         => $regimen,
            'antiguedad'      => $antiguedad,
            'dias_generados'  => $diasGen,
            'dias_utilizados' => $diasUtilizados,
            'dias_saldo'      => $diasSaldo,
            'saldo_acumulado' => $saldoAcumulado,
        ] = $this->calcularCifras($servidor, $anio, $existente);

        $periodo = PeriodoVacacion::updateOrCreate(
            [
                'servidor_id' => $servidor->id,
                'anio'        => $anio,
            ],
            [
                'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
                'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
                'regimen'              => $regimen,
                'anios_antiguedad'     => $antiguedad,
                'dias_generados'       => $diasGen,
                'dias_utilizados'      => $diasUtilizados,
                'dias_saldo'           => $diasSaldo,
                // Arrastre de años anteriores más lo que queda de este, no lo
                // generado: si no, el acumulado ignoraría lo ya gozado.
                'saldo_acumulado'      => $saldoAcumulado + $diasSaldo,
                'estado'               => $existente->estado ?? 'abierto',
            ]
        );

        // Solo el recálculo deliberado sobre un año ya cerrado deja rastro: es
        // el único que altera un saldo certificado.
        if ($forzar && $antes !== null && $existente->estado !== 'abierto') {
            $this->registrarRecalculoForzado($periodo, $antes);
        }

        return $periodo;
    }

    /**
     * ¿El régimen de este servidor genera vacaciones?
     *
     * Se pregunta por la capacidad —`RegimenLaboral::generaVacaciones()`— en
     * vez de comparar cadenas, para que un régimen nuevo tenga que declararlo.
     */
    private function generaVacaciones(Servidor $servidor): bool
    {
        $regimen = $servidor->regimen_laboral instanceof RegimenLaboral
            ? $servidor->regimen_laboral
            : RegimenLaboral::tryFrom((string) ($servidor->regimen_laboral ?? 'losep'));

        return $regimen?->generaVacaciones() ?? true;
    }

    /**
     * Calcula las cifras de un período sin escribir nada.
     *
     * Vive aparte de `generarPeriodo()` porque la previsualización del recálculo
     * forzado necesita exactamente los mismos números que se van a guardar: si se
     * calcularan en dos sitios, el diálogo podría prometer un saldo y la
     * operación dejar otro.
     *
     * @return array{regimen: string, antiguedad: int, dias_generados: float, dias_utilizados: float, dias_saldo: float, saldo_acumulado: float}
     */
    public function calcularCifras(
        Servidor $servidor,
        int $anio,
        ?PeriodoVacacion $existente = null
    ): array {
        $regimen = $servidor->regimen_laboral instanceof RegimenLaboral
            ? $servidor->regimen_laboral->value
            : (string) ($servidor->regimen_laboral ?? 'losep');

        $antiguedad = $this->calcularAntiguedad($servidor, $regimen, $anio);
        $diasGen    = $this->calcularDiasGenerados($regimen, $antiguedad);

        // Saldo acumulado de períodos anteriores abiertos, entero. Antes se
        // recortaba a 60 en LOSEP, pero solo aquí, en lo que se muestra: el
        // saldo real seguía creciendo y el recorte escondía el excedente. El
        // tope se aplica venciendo días (TopeAcumulacionService), no
        // tapándolos.
        $saldoAcumulado = (float) PeriodoVacacion::where('servidor_id', $servidor->id)
            ->where('anio', '<', $anio)
            ->where('estado', 'abierto')
            ->sum('dias_saldo');

        /**
         * Los días ya gozados NO se tocan al regenerar.
         *
         * `generarPeriodo()` se llama tanto para crear el período como para
         * recalcularlo —por ejemplo tras corregir el régimen o la antigüedad de
         * alguien—, y el botón «Generar todos» lo dispara sobre toda la
         * plantilla. Poniendo `dias_utilizados` en cero, una regeneración de
         * rutina habría borrado el consumo de vacaciones de todo el personal y
         * les habría devuelto el saldo íntegro.
         *
         * Regenerar recalcula lo GENERADO —que depende del régimen y la
         * antigüedad, datos que sí pueden corregirse— y respeta lo CONSUMIDO,
         * que es un hecho ya ocurrido y solo cambia aprobando o anulando
         * vacaciones.
         */
        $diasUtilizados = (float) ($existente->dias_utilizados ?? 0);

        // Lo vencido por el tope, igual: una regeneración que lo ignorara
        // devolvería días que Talento Humano ya dio por perdidos.
        $diasVencidos = (float) ($existente->dias_vencidos ?? 0);

        // Se acota a cero: si alguien gozó más de lo que su régimen corregido
        // genera, el saldo es cero, no negativo.
        $diasSaldo = max(0.0, $diasGen - $diasUtilizados - $diasVencidos);

        return [
            'regimen'         => $regimen,
            'antiguedad'      => $antiguedad,
            'dias_generados'  => $diasGen,
            'dias_utilizados' => $diasUtilizados,
            'dias_saldo'      => $diasSaldo,
            'saldo_acumulado' => $saldoAcumulado,
        ];
    }

    /**
     * Qué pasaría al forzar el recálculo de un período, sin tocar nada.
     *
     * Existe para que el diálogo de confirmación pueda decir el número concreto
     * —«el saldo pasará de 30.00 a 15.00 días»— antes de que alguien acepte.
     * Una consecuencia que solo se ve después de aceptarla no es una decisión.
     *
     * @return array{anio: int, estado: string, actual: array<string, float>, propuesto: array<string, float>}|null
     *         `null` si el servidor no tiene período de ese año.
     */
    public function previsualizarRecalculo(Servidor $servidor, int $anio): ?array
    {
        $existente = PeriodoVacacion::where('servidor_id', $servidor->id)
            ->where('anio', $anio)
            ->first();

        if (! $existente) {
            return null;
        }

        $cifras = $this->calcularCifras($servidor, $anio, $existente);

        return [
            'anio'   => $anio,
            'estado' => $existente->estado,
            'actual' => [
                'dias_generados'  => (float) $existente->dias_generados,
                'dias_utilizados' => (float) $existente->dias_utilizados,
                'dias_saldo'      => (float) $existente->dias_saldo,
            ],
            'propuesto' => [
                'dias_generados'  => $cifras['dias_generados'],
                'dias_utilizados' => $cifras['dias_utilizados'],
                'dias_saldo'      => $cifras['dias_saldo'],
            ],
        ];
    }

    /**
     * Deja en la bitácora el recálculo de un período cerrado.
     *
     * Es lo que hace aceptable permitirlo: cambiar un saldo certificado está
     * bien si queda constancia de quién lo hizo y de qué había antes.
     *
     * @param  array<string, mixed>  $antes
     */
    private function registrarRecalculoForzado(PeriodoVacacion $periodo, array $antes): void
    {
        activity('periodos-vacaciones')
            ->performedOn($periodo)
            ->causedBy(auth()->user())
            ->withProperties([
                'anio' => $periodo->anio,
                'estado' => $periodo->estado,
                'antes' => $antes,
                'despues' => $periodo->only([
                    'dias_generados', 'dias_utilizados', 'dias_saldo',
                    'anios_antiguedad', 'regimen',
                ]),
            ])
            ->log('Recálculo forzado de un período cerrado');
    }

    /**
     * Genera períodos para todos los servidores activos.
     * Llamado por el job anual.
     */
    public function generarPeriodosAnuales(int $anio): Collection
    {
        // Se excluyen en la consulta los regímenes que no generan vacaciones:
        // la generación masiva es de rutina y no puede ir lanzando excepciones
        // por cada contrato civil de la plantilla.
        $servidores = Servidor::where('estado', true)
            ->whereNotIn('regimen_laboral', RegimenLaboral::valoresSinVacaciones())
            ->get();
        $resultados = collect();

        foreach ($servidores as $servidor) {
            try {
                $periodo = $this->generarPeriodo($servidor, $anio);
                $resultados->push($periodo);
            } catch (\Exception $e) {
                \Log::error(
                    "Error generando período {$anio} servidor {$servidor->id}: " .
                    $e->getMessage()
                );
            }
        }

        return $resultados;
    }

    /**
     * Descuenta días de un período al aprobar una vacación.
     */
    public function descontarDias(
        int $servidorId,
        float $dias,
        int $anio
    ): void {
        $periodo = PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('anio', $anio)
            ->where('estado', 'abierto')
            ->first();

        if (!$periodo) return;

        $periodo->dias_utilizados += $dias;
        $periodo->recalcularSaldo();
        $periodo->saldo_acumulado  = max(0, $periodo->saldo_acumulado - $dias);

        // Verificar alerta LOSEP
        if ($periodo->debeAlertarLosep()) {
            $periodo->alerta_enviada = true;
            // Aquí se podría disparar un evento/notification
        }

        $periodo->save();
    }

    /**
     * El período abierto de un año, o null si no hay ninguno.
     *
     * `descontarDias()` hace `return` en silencio cuando no lo encuentra, que
     * es correcto para una vacación ya aprobada —no se va a deshacer por eso—
     * pero deja un agujero en permisos: el permiso personal se concede, no
     * descuenta nada, y las horas desaparecen. Quien necesite decidir *antes*
     * de conceder pregunta aquí.
     */
    public function periodoAbierto(int $servidorId, int $anio): ?PeriodoVacacion
    {
        return PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('anio', $anio)
            ->where('estado', 'abierto')
            ->first();
    }

    /**
     * Devuelve días a un período: el inverso exacto de `descontarDias()`.
     *
     * Hace falta para deshacer una confirmación de permiso hecha por error;
     * hasta ahora el descuento era un camino de una sola dirección.
     *
     * Ojo con `saldo_acumulado`: al descontar se le aplica un `max(0, ...)`,
     * así que si ya estaba en cero el descuento no se registró ahí y esta
     * devolución lo sube. La asimetría viene del modelo de períodos, no de
     * aquí — y de que un permiso descuente a la vez de `dias_utilizados` y de
     * `saldo_acumulado`, que parece contarlo dos veces. Se respeta el
     * comportamiento existente en vez de corregirlo de paso.
     */
    public function devolverDias(
        int $servidorId,
        float $dias,
        int $anio
    ): void {
        $periodo = $this->periodoAbierto($servidorId, $anio);

        if (!$periodo || $dias <= 0) return;

        $periodo->dias_utilizados = max(0, $periodo->dias_utilizados - $dias);
        $periodo->recalcularSaldo();
        $periodo->saldo_acumulado = $periodo->saldo_acumulado + $dias;

        $periodo->save();
    }

    /**
     * Devuelve el saldo total disponible del servidor
     * sumando todos los períodos abiertos.
     */
    public function saldoTotal(int $servidorId): float
    {
        return (float) PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('estado', 'abierto')
            ->sum('dias_saldo');
    }

    /**
     * ¿Tiene algún período abierto del que descontar?
     *
     * `saldoTotal()` devuelve cero tanto a quien agotó sus días como a quien no
     * tiene períodos, y cada caso necesita su propio mensaje.
     */
    public function tienePeriodoAbierto(int $servidorId): bool
    {
        return PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('estado', 'abierto')
            ->exists();
    }

    // ── Descuento de vacaciones repartido entre períodos ─────────────

    /**
     * Días disponibles para una vacación que empieza en `$anio`.
     *
     * Cuentan los períodos abiertos de ese año y de los anteriores. Los de un
     * año posterior no: esos días todavía no se han ganado, aunque alguien haya
     * generado el período por adelantado.
     */
    public function saldoHasta(int $servidorId, int $anio): float
    {
        return (float) PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('estado', 'abierto')
            ->where('anio', '<=', $anio)
            ->sum('dias_saldo');
    }

    /**
     * Descuenta los días de una vacación que se aprueba, del período más
     * antiguo al más nuevo.
     *
     * `descontarDias()` tocaba solo el período del año de la vacación: si ese
     * período no alcanzaba, el resto se perdía, aunque hubiera saldo de años
     * anteriores —y era ese saldo el que había dejado pasar la solicitud—.
     * Gozar 20 días con 10 de un año y 15 del siguiente dejaba 10 en vez de 5.
     *
     * Primero se gasta lo más antiguo: es lo que está más cerca de vencer, y
     * lo que se acumula contra el tope. Cada tramo queda anotado en
     * `vacacion_descuentos`, para que una anulación devuelva cada día al
     * período del que salió.
     *
     * Debe llamarse dentro de una transacción: bloquea los períodos para que
     * dos aprobaciones del mismo servidor no lean el mismo saldo a la vez.
     */
    public function consumirParaVacacion(Vacacion $vacacion, float $dias, int $anio): void
    {
        $periodos = PeriodoVacacion::where('servidor_id', $vacacion->servidor_id)
            ->where('estado', 'abierto')
            ->where('anio', '<=', $anio)
            ->orderBy('anio')
            ->lockForUpdate()
            ->get();

        if ($periodos->isEmpty()) {
            throw new ReglaNegocioException(
                "El servidor no tiene un período de vacaciones abierto hasta {$anio}: "
                .'aprobarla no descontaría los días de ningún saldo. Genere el período antes de aprobar.'
            );
        }

        $disponible = (float) $periodos->sum('dias_saldo');

        if ($dias > $disponible) {
            throw new ReglaNegocioException(
                "Saldo insuficiente para aprobar: la solicitud descuenta {$dias} días ".
                "y el saldo disponible hasta {$anio} es de ".number_format($disponible, 2).' días.'
            );
        }

        $restante = $dias;

        foreach ($periodos as $periodo) {
            if ($restante <= 0) {
                break;
            }

            $toma = round(min((float) $periodo->dias_saldo, $restante), 2);

            if ($toma <= 0) {
                continue;
            }

            $periodo->dias_utilizados = (float) $periodo->dias_utilizados + $toma;
            $periodo->recalcularSaldo();
            $periodo->save();

            VacacionDescuento::create([
                'vacacion_id'         => $vacacion->id,
                'periodo_vacacion_id' => $periodo->id,
                'dias'                => $toma,
            ]);

            $restante = round($restante - $toma, 2);
        }

        $this->recalcularAcumulados($vacacion->servidor_id);
    }

    /**
     * Devuelve a cada período lo que una vacación le tomó. Devuelve el total.
     *
     * Solo a períodos abiertos. Si uno ya se cerró, su saldo está certificado:
     * devolverle días sería cambiarlo en silencio, y eso se hace con el
     * recálculo forzado, que deja constancia. Se aborta todo en vez de
     * devolver una parte.
     *
     * Debe llamarse dentro de una transacción.
     */
    public function devolverDeVacacion(Vacacion $vacacion): float
    {
        $descuentos = VacacionDescuento::where('vacacion_id', $vacacion->id)
            ->whereNull('devuelto_en')
            ->lockForUpdate()
            ->get();

        if ($descuentos->isEmpty()) {
            return $this->devolverSinRegistro($vacacion);
        }

        $periodos = PeriodoVacacion::whereIn('id', $descuentos->pluck('periodo_vacacion_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($periodos as $periodo) {
            $this->exigirAbierto($periodo);
        }

        $total = 0.0;

        foreach ($descuentos as $descuento) {
            $periodo = $periodos[$descuento->periodo_vacacion_id];

            $periodo->dias_utilizados = max(0, (float) $periodo->dias_utilizados - $descuento->dias);
            $periodo->recalcularSaldo();
            $periodo->save();

            $descuento->devuelto_en = now();
            $descuento->save();

            $total += $descuento->dias;
        }

        $this->recalcularAcumulados($vacacion->servidor_id);

        return round($total, 2);
    }

    /**
     * Una vacación aprobada antes de que existiera `vacacion_descuentos`.
     *
     * Entonces el descuento iba solo al período de su año, así que ahí se
     * devuelve. Lo que no se sabe es cuánto se tomó de verdad: si el saldo no
     * alcanzaba, lo que faltaba se perdía. Por eso se devuelve lo que dice la
     * solicitud, pero nunca más de lo que ese período tiene utilizado.
     */
    private function devolverSinRegistro(Vacacion $vacacion): float
    {
        $periodo = PeriodoVacacion::where('servidor_id', $vacacion->servidor_id)
            ->where('anio', Carbon::parse($vacacion->fecha_inicio)->year)
            ->lockForUpdate()
            ->first();

        if (! $periodo) {
            return 0.0;
        }

        $this->exigirAbierto($periodo);

        $dias = min((float) $vacacion->dias_solicitados, (float) $periodo->dias_utilizados);

        if ($dias <= 0) {
            return 0.0;
        }

        $periodo->dias_utilizados = (float) $periodo->dias_utilizados - $dias;
        $periodo->recalcularSaldo();
        $periodo->save();

        $this->recalcularAcumulados($vacacion->servidor_id);

        return round($dias, 2);
    }

    private function exigirAbierto(PeriodoVacacion $periodo): void
    {
        if ($periodo->estado !== 'abierto') {
            throw new ReglaNegocioException(
                "El período {$periodo->anio} está {$periodo->estado}: devolverle días cambiaría un saldo "
                .'ya certificado. Si hay que corregirlo, use el recálculo del período.'
            );
        }
    }

    /**
     * Pone al día `saldo_acumulado` en los períodos abiertos del servidor.
     *
     * Es el mismo cálculo de `calcularCifras()` —lo que arrastran los años
     * anteriores más el saldo propio—, pero para todos a la vez: un descuento
     * repartido o un vencimiento cambian el saldo de varios períodos, y el
     * acumulado de cada uno depende de los anteriores.
     */
    public function recalcularAcumulados(int $servidorId): void
    {
        $periodos = PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('estado', 'abierto')
            ->orderBy('anio')
            ->get();

        $arrastre = 0.0;

        foreach ($periodos as $periodo) {
            $periodo->saldo_acumulado = $arrastre + (float) $periodo->dias_saldo;
            $periodo->save();

            $arrastre += (float) $periodo->dias_saldo;
        }
    }

    /**
     * Lo que va en el bloque «Uso exclusivo de Talento Humano» del PDF.
     *
     * La vista leía `dias_derecho` y `periodo_vacaciones`, campos que la
     * solicitud nunca tuvo, así que salían siempre vacíos.
     *
     * - Días de derecho: lo que genera el período del año de la vacación.
     * - Tramos: de qué períodos salieron sus días, si ya se aprobó.
     * - `sin_registro`: aprobada antes de que se anotaran los tramos; entonces
     *   el descuento iba entero al período de su año.
     *
     * @return array{dias_derecho: float|null, tramos: list<array{anio: int, dias: float}>, sin_registro: bool}
     */
    public function paraImpresion(Vacacion $vacacion): array
    {
        $anio = Carbon::parse($vacacion->fecha_inicio)->year;

        $diasDerecho = PeriodoVacacion::where('servidor_id', $vacacion->servidor_id)
            ->where('anio', $anio)
            ->value('dias_generados');

        $tramos = VacacionDescuento::query()
            ->join('periodos_vacaciones as p', 'p.id', '=', 'vacacion_descuentos.periodo_vacacion_id')
            ->where('vacacion_descuentos.vacacion_id', $vacacion->id)
            ->whereNull('vacacion_descuentos.devuelto_en')
            ->orderBy('p.anio')
            ->get(['p.anio', 'vacacion_descuentos.dias'])
            ->map(fn ($t) => ['anio' => (int) $t->anio, 'dias' => (float) $t->dias])
            ->all();

        return [
            'dias_derecho' => $diasDerecho === null ? null : (float) $diasDerecho,
            'tramos'       => $tramos,
            'sin_registro' => $tramos === []
                && in_array((string) $vacacion->estado, ['aprobada', 'gozada'], true),
        ];
    }

    /**
     * Obtiene el resumen de períodos de un servidor.
     */
    public function resumen(int $servidorId): array
    {
        $periodos = PeriodoVacacion::where('servidor_id', $servidorId)
            ->orderByDesc('anio')
            ->get();

        // Calcular desglose por período
        $periodos->each(function ($periodo) use ($servidorId) {
            $anioInicio = \Carbon\Carbon::parse(
                $periodo->fecha_inicio_periodo
            )->startOfDay();
            $anioFin = \Carbon\Carbon::parse(
                $periodo->fecha_fin_periodo
            )->endOfDay();

            // Días de vacaciones que salieron de ESTE período. Se leen del
            // registro de descuentos: desde que el descuento se reparte entre
            // períodos, la fecha de la vacación ya no dice de cuál salió.
            $diasRegistrados = (float) VacacionDescuento::where('periodo_vacacion_id', $periodo->id)
                ->whereNull('devuelto_en')
                ->sum('dias');

            // Las aprobadas antes de que existiera ese registro se descontaban
            // del período de su año, así que se siguen atribuyendo por fecha.
            $diasSinRegistro = (float) Vacacion::where('servidor_id', $servidorId)
                ->whereIn('estado', ['aprobada', 'gozada'])
                ->whereBetween('fecha_inicio', [$anioInicio, $anioFin])
                ->whereIn('motivo', [
                    'vacaciones_anuales',
                    'permiso_cargo_vacaciones',
                ])
                ->whereDoesntHave('descuentos')
                ->sum('dias_solicitados');

            $diasVacaciones = $diasRegistrados + $diasSinRegistro;

            // Días por permisos personales en ese período (horas/8)
            $minutosPermisos = \App\Models\Asistencia\PermisoServidor
                ::where('servidor_id', $servidorId)
                ->where('tipo', 'personal')
                ->whereNotIn('estado', ['anulado', 'pendiente'])
                ->whereBetween('fecha', [$anioInicio, $anioFin])
                ->get()
                ->sum(function ($p) {
                    $hi = substr((string)$p->getRawOriginal('hora_inicio'), 0, 5);
                    $hf = substr((string)$p->getRawOriginal('hora_fin'), 0, 5);
                    [$hI, $mI] = array_map('intval', explode(':', $hi));
                    [$hF, $mF] = array_map('intval', explode(':', $hf));
                    return ($hF * 60 + $mF) - ($hI * 60 + $mI);
                });

            $diasPermisos = round($minutosPermisos / 480, 4);

            // Asignar atributos dinámicos al período
            $periodo->dias_vacaciones_aprobadas = round((float)$diasVacaciones, 2);
            $periodo->dias_permisos_personales  = $diasPermisos;
        });

        // La alerta mira el tope del régimen del servidor. Antes era «45 días»
        // para todos, con el mensaje del tope LOSEP también para el Código del
        // Trabajo, cuyo tope es otro.
        $tope = app(TopeAcumulacionService::class)->estado(Servidor::findOrFail($servidorId));

        return [
            'periodos'                   => $periodos,
            'saldo_total'                => $tope['saldo'],
            'alerta_limite'              => $tope['alerta'],
            'tope'                       => $tope['tope'],
            'excedente'                  => $tope['excedente'],
            'total_vacaciones_aprobadas' => round(
                $periodos->sum('dias_vacaciones_aprobadas'), 2
            ),
            'total_permisos_personales'  => round(
                $periodos->sum('dias_permisos_personales'), 4
            ),
        ];
    }
}

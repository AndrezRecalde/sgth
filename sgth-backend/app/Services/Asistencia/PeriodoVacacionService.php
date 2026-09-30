<?php
namespace App\Services\Asistencia;

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\PermisoDescuento;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Asistencia\VacacionDescuento;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PeriodoVacacionService
{
    /**
     * Servidores por lote en la generación masiva.
     *
     * Ni tan pocos que la consulta se repita mil veces, ni tantos que volvamos
     * a tener media plantilla en memoria.
     */
    private const SERVIDORES_POR_LOTE = 200;

    /**
     * Calcula los días generados según régimen y antigüedad.
     */
    public function calcularDiasGenerados(
        string $regimen,
        int $aniosAntiguedad
    ): float {
        // La escala vive en un solo sitio, que también consultan los motores.
        // Aquí se aplicaba 15/20/25/30 en LOSEP y, en el Código del Trabajo,
        // el día adicional desde el segundo año: ver `EscalaVacaciones`.
        return EscalaVacaciones::diasGenerados($regimen, $aniosAntiguedad);
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
     * Genera los períodos de todos los servidores activos. Devuelve cuántos.
     *
     * Los regímenes que no generan vacaciones se excluyen en la consulta: la
     * generación masiva es de rutina y no puede ir lanzando una excepción por
     * cada contrato civil de la plantilla.
     *
     * Va por lotes y devuelve un número, no los modelos. Antes cargaba la
     * plantilla entera con un `->get()` y además iba acumulando cada período
     * creado en una colección que nadie leía: los dos únicos llamadores —el
     * endpoint y el job anual— solo preguntan cuántos. Con mil servidores eso
     * eran dos mil modelos vivos a la vez para devolver un entero, y el
     * endpoint lo hace dentro de una petición web.
     */
    public function generarPeriodosAnuales(int $anio): int
    {
        $generados = 0;

        Servidor::where('estado', true)
            ->whereNotIn('regimen_laboral', RegimenLaboral::valoresSinVacaciones())
            ->chunkById(self::SERVIDORES_POR_LOTE, function (EloquentCollection $servidores) use ($anio, &$generados) {
                foreach ($servidores as $servidor) {
                    try {
                        $this->generarPeriodo($servidor, $anio);
                        $generados++;
                    } catch (\Exception $e) {
                        // Un servidor con datos incompletos no puede parar la
                        // generación de los demás.
                        \Log::error(
                            "Error generando período {$anio} servidor {$servidor->id}: "
                            .$e->getMessage()
                        );
                    }
                }
            });

        return $generados;
    }

    /**
     * El período abierto de un año, o null si no hay ninguno.
     *
     * Quien necesite decidir *antes* de conceder un permiso pregunta aquí: un
     * permiso personal que se concede sin período del que descontar se concede
     * gratis, y las horas desaparecen.
     */
    public function periodoAbierto(int $servidorId, int $anio): ?PeriodoVacacion
    {
        return PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('anio', $anio)
            ->where('estado', 'abierto')
            ->first();
    }

    /**
     * Devuelve días a un período de un año concreto.
     *
     * Solo la usa la rama antigua de `devolverDePermiso()`: la de los permisos
     * confirmados antes de que existiera `permiso_descuentos`, cuando todo
     * salía del período del año del permiso. Lo de ahora se devuelve tramo por
     * tramo, con `devolverTramos()`.
     *
     * Ojo con `saldo_acumulado`: lo sube sin haberlo bajado necesariamente al
     * descontar. La asimetría viene del modelo de períodos y se respeta en vez
     * de corregirla de paso.
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
     * El saldo disponible HOY: los períodos abiertos de este año y de antes.
     *
     * Sumaba todos los períodos abiertos, los de años futuros incluidos, y de
     * ahí salían dos cosas malas.
     *
     * La grave: el tope de acumulación se mide sobre este saldo, y vencer el
     * excedente quita días de los períodos MÁS ANTIGUOS. Un período generado por
     * adelantado inflaba el acumulado, podía inventar un excedente, y al
     * vencerlo se perdían días reales —ya ganados— por otros que todavía no lo
     * estaban. Vencer no se deshace.
     *
     * La cotidiana: el portal decía «tiene 85 días» y, al pedir vacaciones para
     * hoy, la solicitud se rechazaba por saldo insuficiente. Quien aprueba mira
     * `saldoHasta()`, que nunca contó los períodos futuros, con su motivo
     * escrito: esos días todavía no se han ganado, aunque alguien haya generado
     * el período por adelantado. Ahora lo que se muestra y lo que se permite
     * dicen lo mismo.
     */
    public function saldoTotal(int $servidorId): float
    {
        return $this->saldoHasta($servidorId, (int) now()->year);
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

    /**
     * ¿Tiene algún período abierto de `$anio` o de antes?
     *
     * Es de donde puede salir un descuento: un período generado por adelantado
     * para un año posterior no cuenta, igual que en `saldoHasta()`.
     */
    public function tienePeriodoAbiertoHasta(int $servidorId, int $anio): bool
    {
        return PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('estado', 'abierto')
            ->where('anio', '<=', $anio)
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
     * Antes se tocaba solo el período del año de la vacación: si ese período no
     * alcanzaba, el resto se perdía, aunque hubiera saldo de años anteriores —y
     * era ese saldo el que había dejado pasar la solicitud—. Gozar 20 días con
     * 10 de un año y 15 del siguiente dejaba 10 en vez de 5.
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
        $periodos = $this->periodosAbiertosHasta($vacacion->servidor_id, $anio);

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

        $this->repartirEntrePeriodos(
            $periodos,
            $dias,
            fn (PeriodoVacacion $periodo, float $toma) => VacacionDescuento::create([
                'vacacion_id'         => $vacacion->id,
                'periodo_vacacion_id' => $periodo->id,
                'dias'                => $toma,
            ])
        );

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

        $total = $this->devolverTramos($descuentos);

        $this->recalcularAcumulados($vacacion->servidor_id);

        return $total;
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

    // ── Descuento de permisos personales repartido entre períodos ────

    /**
     * Descuenta las horas de un permiso personal que se confirma, del período
     * más antiguo al más nuevo, igual que una vacación.
     *
     * Antes salían solo del período del año del permiso: si ese estaba vacío
     * el permiso se rechazaba aunque hubiera saldo de años anteriores, y
     * mientras tanto esos días viejos seguían acercándose al tope. Cada tramo
     * queda en `permiso_descuentos`, para que revertir la confirmación lo
     * devuelva a su período sin recalcular nada.
     *
     * Debe llamarse dentro de una transacción: bloquea los períodos.
     */
    public function consumirParaPermiso(PermisoServidor $permiso, float $dias, int $anio): void
    {
        $periodos = $this->periodosAbiertosHasta($permiso->servidor_id, $anio);

        if ($periodos->isEmpty()) {
            throw new ReglaNegocioException(
                "No hay un período de vacaciones abierto hasta {$anio} para este servidor: "
                .'el permiso personal no puede descontarse de ningún saldo.'
            );
        }

        $disponible = (float) $periodos->sum('dias_saldo');

        // El saldo se miró al registrar el permiso, pero un pendiente no
        // reserva horas: dos que pasaron el control cada uno por su lado
        // podían, juntos, superarlo. Con los períodos bloqueados, el segundo ve
        // el saldo que dejó el primero.
        if ($dias > $disponible) {
            throw new ReglaNegocioException(sprintf(
                'Saldo de vacaciones insuficiente para confirmar el permiso %s: descuenta %s días '.
                'y al servidor le quedan %s hasta %d. Otro permiso o unas vacaciones usaron el saldo '.
                'desde que este se registró.',
                $permiso->folio,
                number_format($dias, 2),
                number_format($disponible, 2),
                $anio
            ));
        }

        $this->repartirEntrePeriodos(
            $periodos,
            $dias,
            fn (PeriodoVacacion $periodo, float $toma) => PermisoDescuento::create([
                'permiso_servidor_id' => $permiso->id,
                'periodo_vacacion_id' => $periodo->id,
                'dias'                => $toma,
            ])
        );

        $this->recalcularAcumulados($permiso->servidor_id);
    }

    /**
     * Devuelve a cada período lo que un permiso le tomó.
     *
     * Un permiso confirmado antes de que existiera `permiso_descuentos` no
     * tiene tramos: entonces todo salió del período de su año, y ahí se
     * devuelve `$diasSinRegistro`, como se hacía hasta ahora.
     *
     * Debe llamarse dentro de una transacción.
     */
    public function devolverDePermiso(PermisoServidor $permiso, float $diasSinRegistro): void
    {
        $descuentos = PermisoDescuento::where('permiso_servidor_id', $permiso->id)
            ->whereNull('devuelto_en')
            ->lockForUpdate()
            ->get();

        if ($descuentos->isEmpty()) {
            $this->devolverDias(
                $permiso->servidor_id,
                $diasSinRegistro,
                Carbon::parse($permiso->fecha)->year
            );

            return;
        }

        $this->devolverTramos($descuentos);

        $this->recalcularAcumulados($permiso->servidor_id);
    }

    // ── Apoyos del reparto ───────────────────────────────────────────

    /**
     * Los períodos abiertos de un servidor hasta `$anio`, del más antiguo al
     * más nuevo, bloqueados. Los de un año posterior no cuentan: esos días
     * todavía no se han ganado, aunque el período se haya generado antes.
     */
    private function periodosAbiertosHasta(int $servidorId, int $anio): EloquentCollection
    {
        return PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('estado', 'abierto')
            ->where('anio', '<=', $anio)
            ->orderBy('anio')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Toma `$dias` de los períodos en el orden recibido y anota cada tramo con
     * `$anotar`. Quien llama ya comprobó que el saldo alcanza.
     *
     * Primero se gasta lo más antiguo: es lo que está más cerca de vencer, y lo
     * que se acumula contra el tope.
     */
    private function repartirEntrePeriodos(EloquentCollection $periodos, float $dias, callable $anotar): void
    {
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

            $anotar($periodo, $toma);

            $restante = round($restante - $toma, 2);
        }
    }

    /**
     * Devuelve cada tramo —de una vacación o de un permiso— a su período y lo
     * marca devuelto. Devuelve el total.
     *
     * Solo a períodos abiertos. Si uno ya se cerró, su saldo está certificado:
     * devolverle días sería cambiarlo en silencio, y eso se hace con el
     * recálculo forzado, que deja constancia. Se aborta todo en vez de
     * devolver una parte.
     */
    private function devolverTramos(EloquentCollection $descuentos): float
    {
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

        return round($total, 2);
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
     * Resumen de períodos de un servidor: qué generó cada uno, de dónde
     * salieron los días gozados y cómo va contra su tope.
     */
    public function resumen(int $servidorId): array
    {
        $servidor = Servidor::findOrFail($servidorId);

        $periodos = PeriodoVacacion::where('servidor_id', $servidorId)
            ->orderByDesc('anio')
            ->get();

        $this->repartirConsumoEntrePeriodos($servidor, $periodos);

        // La alerta mira el tope del régimen del servidor. Antes era «45 días»
        // para todos, con el mensaje del tope LOSEP también para el Código del
        // Trabajo, cuyo tope es otro.
        $tope = app(TopeAcumulacionService::class)->estado($servidor);

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

    /**
     * Anota en cada período cuántos días le tomaron las vacaciones y cuántos
     * los permisos personales.
     *
     * Los dos se leen de los registros de descuento —`vacacion_descuentos` y
     * `permiso_descuentos`—, que es lo único que dice de qué período salió cada
     * día: el descuento se reparte del período más antiguo al más nuevo, así
     * que la fecha de la solicitud no lo dice.
     *
     * Los permisos se atribuían por fecha, y eso traía tres errores a la vez:
     *
     * - Contaba los rechazados y las faltas injustificadas. Los dos estados
     *   salen de «pendiente» sin pasar por la confirmación, que es lo único que
     *   descuenta: la pantalla mostraba como gastados días que seguían en el
     *   saldo.
     * - Contaba los del Código del Trabajo, que no descuentan de vacaciones
     *   (`PermisoService::descuentaVacaciones()` es solo LOSEP).
     * - Atribuía las horas al año del permiso y no al período del que de
     *   verdad salieron.
     *
     * Con los registros de descuento los tres desaparecen de una vez, porque
     * un permiso que no descontó no tiene fila que sumar. Queda la atribución
     * por fecha solo para lo anterior a esos registros —vacaciones de antes del
     * 2026-09-10 y permisos de antes del 2026-09-11—, que se descontaba entero
     * del período de su año.
     *
     * Antes esto hacía tres consultas por período más un `get()` de permisos
     * cuyos minutos se sumaban en PHP: con ocho años de historial eran unas
     * veinticinco consultas para una pantalla de solo lectura. Ahora son cuatro,
     * pase lo que pase.
     *
     * @param  EloquentCollection<int, PeriodoVacacion>  $periodos
     */
    private function repartirConsumoEntrePeriodos(
        Servidor $servidor,
        EloquentCollection $periodos
    ): void {
        if ($periodos->isEmpty()) {
            return;
        }

        $ids = $periodos->pluck('id');

        $vacacionesPorPeriodo = $this->descuentosPorPeriodo(VacacionDescuento::query(), $ids);
        $permisosPorPeriodo   = $this->descuentosPorPeriodo(PermisoDescuento::query(), $ids);

        $vacacionesSinRegistro = $this->vacacionesSinRegistro($servidor->id);
        $permisosSinRegistro   = $this->permisosSinRegistro($servidor);

        foreach ($periodos as $periodo) {
            $desde = Carbon::parse($periodo->fecha_inicio_periodo)->startOfDay();
            $hasta = Carbon::parse($periodo->fecha_fin_periodo)->endOfDay();

            $dentro = fn (Collection $filas, string $campo): Collection => $filas
                ->filter(fn ($fila) => Carbon::parse($fila->$campo)->betweenIncluded($desde, $hasta));

            $diasVacaciones = ($vacacionesPorPeriodo[$periodo->id] ?? 0.0)
                + (float) $dentro($vacacionesSinRegistro, 'fecha_inicio')->sum('dias_solicitados');

            $minutosPermisos = (int) $dentro($permisosSinRegistro, 'fecha')->sum(
                fn ($p) => max(0, JornadaLaboral::minutosEntre(
                    (string) $p->getRawOriginal('hora_inicio'),
                    (string) $p->getRawOriginal('hora_fin'),
                ))
            );

            $diasPermisos = ($permisosPorPeriodo[$periodo->id] ?? 0.0)
                + JornadaLaboral::aDias($minutosPermisos);

            $periodo->dias_vacaciones_aprobadas = round($diasVacaciones, 2);
            $periodo->dias_permisos_personales  = round($diasPermisos, 4);
        }
    }

    /**
     * Días vivos que cada período le prestó, en una sola consulta.
     *
     * Solo los tramos no devueltos: al anular una vacación o revertir la
     * confirmación de un permiso, su tramo se marca `devuelto_en` y esos días
     * vuelven al saldo, así que ya no cuentan como gastados.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $periodoIds
     * @return \Illuminate\Support\Collection<int, float>  indexada por período
     */
    private function descuentosPorPeriodo(
        \Illuminate\Database\Eloquent\Builder $consulta,
        Collection $periodoIds
    ): Collection {
        return $consulta
            ->whereIn('periodo_vacacion_id', $periodoIds)
            ->whereNull('devuelto_en')
            ->groupBy('periodo_vacacion_id')
            ->selectRaw('periodo_vacacion_id, SUM(dias) AS dias')
            ->pluck('dias', 'periodo_vacacion_id')
            ->map(fn ($dias) => (float) $dias);
    }

    /**
     * Vacaciones gozadas antes de que se anotaran los tramos: entonces todo
     * salía del período de su año, y ahí se siguen atribuyendo.
     *
     * @return Collection<int, Vacacion>
     */
    private function vacacionesSinRegistro(int $servidorId): Collection
    {
        return Vacacion::where('servidor_id', $servidorId)
            ->whereIn('estado', ['aprobada', 'gozada'])
            ->whereIn('motivo', ['vacaciones_anuales', 'permiso_cargo_vacaciones'])
            ->whereDoesntHave('descuentos')
            ->get(['fecha_inicio', 'dias_solicitados'])
            ->toBase();
    }

    /**
     * Permisos personales que descontaron antes de que se anotaran los tramos.
     *
     * Se piden los mismos tres requisitos que exige el descuento —personal,
     * LOSEP y confirmado—, porque aquí no hay tramo que lo demuestre. De ahí el
     * corte por régimen: si el servidor no es LOSEP, su permiso no descontó
     * nunca y no hay nada que atribuir.
     *
     * @return Collection<int, PermisoServidor>
     */
    private function permisosSinRegistro(Servidor $servidor): Collection
    {
        if (! $this->descuentaPermisosDeVacaciones($servidor)) {
            return collect();
        }

        return PermisoServidor::where('servidor_id', $servidor->id)
            ->where('tipo', TipoPermiso::PERSONAL->value)
            ->whereIn('estado', [
                EstadoPermiso::ACTIVO->value,
                EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value,
            ])
            ->whereDoesntHave('descuentos')
            ->get(['fecha', 'hora_inicio', 'hora_fin'])
            ->toBase();
    }

    /**
     * ¿Los permisos personales de este servidor descuentan de sus vacaciones?
     *
     * Espeja `PermisoService::descuentaVacaciones()`: solo LOSEP. El Código del
     * Trabajo se rige por su contrato colectivo.
     */
    private function descuentaPermisosDeVacaciones(Servidor $servidor): bool
    {
        $regimen = $servidor->regimen_laboral instanceof RegimenLaboral
            ? $servidor->regimen_laboral
            : RegimenLaboral::tryFrom((string) ($servidor->regimen_laboral ?? 'losep'));

        return $regimen === RegimenLaboral::LOSEP;
    }
}

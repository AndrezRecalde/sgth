<?php
namespace App\Services\Asistencia;

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PeriodoVacacion;
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

        // Saldo acumulado de períodos anteriores abiertos.
        $saldoAcumulado = (float) PeriodoVacacion::where('servidor_id', $servidor->id)
            ->where('anio', '<', $anio)
            ->where('estado', 'abierto')
            ->sum('dias_saldo');

        // Límite de acumulación LOSEP (60 días).
        if ($regimen === 'losep') {
            $saldoAcumulado = min($saldoAcumulado, 60.0);
        }

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

        // Se acota a cero igual que en `descontarDias()`: si alguien gozó más
        // de lo que su régimen corregido genera, el saldo es cero, no negativo.
        $diasSaldo = max(0.0, $diasGen - $diasUtilizados);

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
        $periodo->dias_saldo       = max(0, $periodo->dias_generados - $periodo->dias_utilizados);
        $periodo->saldo_acumulado  = max(0, $periodo->saldo_acumulado - $dias);

        // Verificar alerta LOSEP
        if ($periodo->debeAlertarLosep()) {
            $periodo->alerta_enviada = true;
            // Aquí se podría disparar un evento/notification
        }

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

            // Días por vacaciones aprobadas en ese período
            $diasVacaciones = \App\Models\Asistencia\Vacacion
                ::where('servidor_id', $servidorId)
                ->whereIn('estado', ['aprobada', 'gozada'])
                ->whereBetween('fecha_inicio', [$anioInicio, $anioFin])
                ->whereIn('motivo', [
                    'vacaciones_anuales',
                    'permiso_cargo_vacaciones',
                ])
                ->sum('dias_solicitados');

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

        $saldoTotal = $this->saldoTotal($servidorId);

        return [
            'periodos'                   => $periodos,
            'saldo_total'                => $saldoTotal,
            'alerta_limite'              => $saldoTotal >= 45,
            'total_vacaciones_aprobadas' => round(
                $periodos->sum('dias_vacaciones_aprobadas'), 2
            ),
            'total_permisos_personales'  => round(
                $periodos->sum('dias_permisos_personales'), 4
            ),
        ];
    }
}

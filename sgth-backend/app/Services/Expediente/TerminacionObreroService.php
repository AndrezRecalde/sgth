<?php

namespace App\Services\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\ActaFiniquito;
use App\Models\Expediente\ContratoColectivo;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\SalarioBasico;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * La terminación del contrato de un obrero (diseño de Acciones de Personal,
 * 9.15; fase 4.5): sus causales del Código del Trabajo, la liquidación
 * referencial de lo que el Código fija y el acta de finiquito.
 *
 * La liquidación es una referencia, como el descuento de una multa: la calcula
 * el sistema con la remuneración y los años de servicio del acto, y la paga
 * Financiero con la suya. Lleva solo lo que fija el Código: la bonificación por
 * desahucio (Art. 185) y la indemnización por despido intempestivo (Art. 188).
 * La jubilación patronal y el retiro voluntario los fija el contrato colectivo
 * o la ordenanza (fase 4.6), y los haberes ordinarios —décimos, vacaciones no
 * gozadas— son de la desvinculación de todos (fase 7).
 *
 * Desde la fase 4.5b, también lo que se paga por el reintegro del obrero cuyo
 * despido el juez declaró ineficaz (Art. 195.3): las remuneraciones pendientes
 * con el 10 % de recargo, por el mismo aviso a Financiero.
 *
 * Y desde la 4.6, la bonificación por jubilación o por retiro voluntario que
 * pacte el contrato colectivo vigente, en salarios básicos por año de servicio
 * y con el tope de 210 del Mandato 2.
 */
class TerminacionObreroService
{
    /** El período de prueba del obrero: hasta 90 días (Código del Trabajo, Art. 15). */
    public const DIAS_DE_PRUEBA = 90;

    /** El aviso del desahucio (Código del Trabajo, Art. 184). */
    public const DIAS_DE_AVISO_DEL_DESAHUCIO = 15;

    /** El tope de la indemnización por despido intempestivo, en remuneraciones (Art. 188). */
    private const MESES_MAXIMOS_DE_INDEMNIZACION = 25;

    /** El recargo sobre las remuneraciones pendientes del despido ineficaz (Art. 195.3). */
    private const RECARGO_DEL_DESPIDO_INEFICAZ = 0.10;

    /** El tope de las bonificaciones del Mandato 2, Art. 8, en salarios básicos. */
    private const TOPE_DEL_MANDATO_2 = 210;

    /**
     * Valida la causal del obrero y deja la fecha en que presentó la renuncia
     * solo donde significa algo. Al preparar y al corregir el borrador.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function completar(Servidor $servidor, ?SubtipoMovimientoPersonal $causal, array $datos): array
    {
        if ($causal !== SubtipoMovimientoPersonal::DESAHUCIO) {
            return [...$datos, 'fecha_presentacion_renuncia' => null];
        }

        $presentada = $datos['fecha_presentacion_renuncia'] ?? null;

        if (blank($presentada)) {
            throw new ReglaNegocioException(
                'Indique la fecha en que el obrero presentó su renuncia: el desahucio se da con '
                    .self::DIAS_DE_AVISO_DEL_DESAHUCIO.' días de aviso (Código del Trabajo, Art. 184).'
            );
        }

        $presentada = Carbon::parse($presentada)->toDateString();

        if ($presentada > now()->toDateString()) {
            throw new ReglaNegocioException('La renuncia no puede presentarse en una fecha futura.');
        }

        if (filled($datos['fecha_efectiva'] ?? null) && $presentada > Carbon::parse($datos['fecha_efectiva'])->toDateString()) {
            throw new ReglaNegocioException('La renuncia se presenta antes de que rija la terminación.');
        }

        return [...$datos, 'fecha_presentacion_renuncia' => $presentada];
    }

    /**
     * El período de prueba del obrero no pasa de 90 días desde que empezó el
     * contrato (Art. 15). Pasados, la causal no procede.
     */
    public function validarPeriodoDePrueba(?ContratoServidor $vigente, ?string $rige): void
    {
        if (! $vigente?->fecha_inicio || blank($rige)) {
            return;
        }

        $fin = $vigente->fecha_inicio->copy()->addDays(self::DIAS_DE_PRUEBA - 1);

        if (Carbon::parse($rige)->gt($fin)) {
            throw new ReglaNegocioException(
                'El período de prueba del obrero es de hasta '.self::DIAS_DE_PRUEBA.' días (Código del Trabajo, '
                    .'Art. 15): terminó el '.$fin->format('d/m/Y').', y la causal ya no procede.'
            );
        }
    }

    /**
     * ¿El desahucio rige antes de los 15 días de aviso? Se avisa, no se
     * impide: el empleador puede aceptarlo antes.
     */
    public static function desahucioSinElAviso(MovimientoPersonal $movimiento): bool
    {
        return $movimiento->subtipoEfectivo() === SubtipoMovimientoPersonal::DESAHUCIO
            && $movimiento->fecha_presentacion_renuncia
            && $movimiento->fecha_efectiva
            && $movimiento->fecha_presentacion_renuncia->diffInDays($movimiento->fecha_efectiva, false)
                < self::DIAS_DE_AVISO_DEL_DESAHUCIO;
    }

    /**
     * La liquidación referencial de la terminación: los rubros que fija el
     * Código del Trabajo para su causal, sobre la remuneración que el acto
     * congeló y los años de servicio hasta el día en que rige. Null si la
     * causal no tiene ninguno.
     *
     * Los años cuentan con su fracción en la bonificación (Art. 185: por cada
     * año); en la indemnización, la fracción de un año es un año completo (Art.
     * 188).
     *
     * El reintegro por despido ineficaz trae la suya (fase 4.5b): `tipo` dice
     * cuál es, y en el reintegro `desde` y `hasta` son los días que estuvo
     * fuera.
     *
     * @return array{tipo: 'terminacion'|'reintegro', remuneracion: ?float, desde: ?string, hasta: ?string,
     *               anios_servicio: ?float, dias_fuera: ?int,
     *               rubros: list<array{concepto: string, base_legal: string, detalle: string, monto: ?float}>,
     *               total: ?float, nota: string}|null
     */
    public function liquidacion(MovimientoPersonal $movimiento): ?array
    {
        if ($movimiento->esReintegroPorDespidoIneficaz()) {
            return $this->liquidacionDelReintegro($movimiento);
        }

        $causal = $movimiento->subtipoEfectivo();

        if ($movimiento->instrumento !== InstrumentoActo::DOCUMENTO_CT
            || ! ($causal?->pagaBonificacionPorDesahucio() || $causal?->clausulaDelContratoColectivo())
        ) {
            return null;
        }

        // La del contrato colectivo, si la pacta (fase 4.6). Sin ella, la
        // jubilación y el retiro no tienen nada que el sistema calcule.
        $contrato = $causal->clausulaDelContratoColectivo()
            ? app(ContratoColectivoService::class)->vigente($movimiento->fecha_efectiva)
            : null;
        $salariosPorAnio = match ($causal->clausulaDelContratoColectivo()) {
            'jubilacion' => $contrato?->bonificacion_jubilacion_sbu,
            'retiro'     => $contrato?->bonificacion_retiro_sbu,
            default      => null,
        };

        if (! $causal->pagaBonificacionPorDesahucio() && $salariosPorAnio === null) {
            return null;
        }

        $remuneracion = $movimiento->remuneracion_origen !== null ? (float) $movimiento->remuneracion_origen : null;
        $desde = Servidor::find($movimiento->servidor_id)?->inicioDelServicioContinuo();
        $hasta = $movimiento->fecha_efectiva;

        $anios = $desde && $hasta
            ? round(Carbon::parse($desde)->diffInDays($hasta->copy()->addDay()) / 365, 2)
            : null;

        $rubros = [];

        if ($causal === SubtipoMovimientoPersonal::DESPIDO_INTEMPESTIVO) {
            $meses = $anios === null ? null : ($anios <= 3 ? 3 : min((int) ceil($anios), self::MESES_MAXIMOS_DE_INDEMNIZACION));

            $rubros[] = [
                'concepto'   => 'Indemnización por despido intempestivo',
                'base_legal' => 'Código del Trabajo, Art. 188',
                'detalle'    => $meses === null ? 'Sin los años de servicio' : "{$meses} remuneraciones",
                'monto'      => $meses !== null && $remuneracion !== null ? round($remuneracion * $meses, 2) : null,
            ];
        }

        if ($causal->pagaBonificacionPorDesahucio()) {
            $rubros[] = [
                'concepto'   => 'Bonificación por desahucio',
                'base_legal' => 'Código del Trabajo, Art. 185',
                'detalle'    => $anios === null
                    ? 'Sin los años de servicio'
                    : '25 % de la última remuneración por '.number_format($anios, 2, ',', '.').' años de servicio',
                'monto'      => $anios !== null && $remuneracion !== null ? round($remuneracion * 0.25 * $anios, 2) : null,
            ];
        }

        if ($salariosPorAnio !== null) {
            $rubros[] = $this->bonificacionDelContratoColectivo($causal, $contrato, (float) $salariosPorAnio, $anios, $hasta);
        }

        $montos = array_column($rubros, 'monto');

        return [
            'tipo'           => 'terminacion',
            'remuneracion'   => $remuneracion,
            'desde'          => $desde,
            'hasta'          => null,
            'anios_servicio' => $anios,
            'dias_fuera'     => null,
            'rubros'         => $rubros,
            'total'          => in_array(null, $montos, true) ? null : round(array_sum($montos), 2),
            // Lo del contrato colectivo que el sistema guarda ya va en los
            // rubros (fase 4.6).
            'nota'           => 'Referencial. No incluye los décimos, las vacaciones no gozadas ni las cláusulas del '
                .'contrato colectivo que el sistema no guarda: el valor definitivo lo establece Financiero.',
        ];
    }

    /**
     * La bonificación por jubilación o por retiro voluntario del contrato
     * colectivo (fase 4.6): sus salarios básicos por año de servicio, con la
     * fracción, hasta 210 (Mandato 2, Art. 8), al salario básico del año en
     * que termina.
     *
     * @return array{concepto: string, base_legal: string, detalle: string, monto: ?float}
     */
    private function bonificacionDelContratoColectivo(
        SubtipoMovimientoPersonal $causal,
        ContratoColectivo $contrato,
        float $salariosPorAnio,
        ?float $anios,
        ?Carbon $hasta,
    ): array {
        $salarios = $anios === null ? null : min(round($salariosPorAnio * $anios, 2), (float) self::TOPE_DEL_MANDATO_2);
        $anio = $hasta?->year;
        $basico = $anio ? SalarioBasico::delAnio($anio) : null;

        $detalle = match (true) {
            $salarios === null => 'Sin los años de servicio',
            default            => number_format($salariosPorAnio, 2, ',', '.').' salarios básicos por '
                .number_format($anios, 2, ',', '.').' años de servicio: '.number_format($salarios, 2, ',', '.')
                .($salarios >= self::TOPE_DEL_MANDATO_2 ? ' (el tope)' : '')
                .($basico === null
                    ? ", sin el salario básico de {$anio} registrado"
                    : ' de $'.number_format($basico, 2, ',', '.')),
        };

        return [
            'concepto'   => $causal->clausulaDelContratoColectivo() === 'jubilacion'
                ? 'Bonificación por jubilación'
                : 'Bonificación por retiro voluntario',
            'base_legal' => "Contrato colectivo N.º {$contrato->numero}; Mandato Constituyente 2, Art. 8",
            'detalle'    => $detalle,
            'monto'      => $salarios !== null && $basico !== null ? round($salarios * $basico, 2) : null,
        ];
    }

    /**
     * Lo que se paga por el reintegro del obrero cuyo despido el juez declaró
     * ineficaz (Art. 195.3): las remuneraciones pendientes, desde el día
     * siguiente a la terminación hasta la víspera del reintegro, con el 10 %
     * de recargo. Las que fije la sentencia, si Talento Humano las registró;
     * si no, la remuneración que tenía, a treinta días por mes.
     *
     * @return array{tipo: 'reintegro', remuneracion: ?float, desde: ?string, hasta: ?string,
     *               anios_servicio: null, dias_fuera: ?int,
     *               rubros: list<array{concepto: string, base_legal: string, detalle: string, monto: ?float}>,
     *               total: ?float, nota: string}
     */
    private function liquidacionDelReintegro(MovimientoPersonal $reintegro): array
    {
        $terminacion = $reintegro->movimientoRelacionado;
        $remuneracion = app(RestitucionService::class)->remuneracionQueTenia($reintegro);

        $desde = $terminacion?->fecha_efectiva?->copy()->addDay();
        $hasta = $reintegro->fecha_efectiva?->copy()->subDay();
        $dias = $desde && $hasta ? max(0, (int) $desde->diffInDays($hasta, false) + 1) : null;

        $fijadas = $reintegro->remuneraciones_dejadas_de_percibir !== null
            ? (float) $reintegro->remuneraciones_dejadas_de_percibir
            : null;
        $pendientes = $fijadas
            ?? ($remuneracion !== null && $dias !== null ? round($remuneracion / 30 * $dias, 2) : null);
        $recargo = $pendientes !== null ? round($pendientes * self::RECARGO_DEL_DESPIDO_INEFICAZ, 2) : null;

        $nota = 'Referencial.';
        if (! $reintegro->fecha_sentencia) {
            $nota .= ' Lo ordenó la providencia como medida cautelar: lo pendiente se paga si la sentencia declara '
                .'la ineficacia del despido.';
        }
        if ($acta = $terminacion?->actaFiniquito) {
            $nota .= ' En el finiquito de la terminación se pagaron $'.number_format((float) $acta->valor_pagado, 2)
                .': se compensan según lo que disponga la sentencia.';
        }
        $nota .= ' El valor definitivo lo establece Financiero.';

        return [
            'tipo'           => 'reintegro',
            'remuneracion'   => $remuneracion,
            'desde'          => $dias ? $desde->toDateString() : null,
            'hasta'          => $dias ? $hasta->toDateString() : null,
            'anios_servicio' => null,
            'dias_fuera'     => $dias,
            'rubros'         => [
                [
                    'concepto'   => 'Remuneraciones pendientes',
                    'base_legal' => 'Código del Trabajo, Art. 195.3',
                    'detalle'    => $fijadas !== null
                        ? 'Las que fija la sentencia'
                        : ($dias === null ? 'Sin los días fuera' : "{$dias} días fuera, a treinta por mes"),
                    'monto'      => $pendientes,
                ],
                [
                    'concepto'   => 'Recargo del 10 %',
                    'base_legal' => 'Código del Trabajo, Art. 195.3',
                    'detalle'    => '10 % de las remuneraciones pendientes',
                    'monto'      => $recargo,
                ],
            ],
            'total'          => $pendientes !== null ? round($pendientes + $recargo, 2) : null,
            'nota'           => $nota,
        ];
    }

    /**
     * Las terminaciones de obreros ya registradas, con su liquidación y su
     * acta de finiquito, de la más reciente a la más antigua.
     *
     * La que el juez dejó sin efecto con un reintegro ya registrado lo dice, y
     * no está pendiente de acta (fase 4.5b).
     * @return list<array<string, mixed>>
     */
    public function listar(bool $soloSinActa = false): array
    {
        return MovimientoPersonal::query()
            ->where('instrumento', InstrumentoActo::DOCUMENTO_CT->value)
            ->where('tipo_movimiento', TipoMovimientoPersonal::CESACION_FUNCIONES->value)
            ->whereIn('estado', [EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value])
            ->when($soloSinActa, fn ($q) => $q->whereDoesntHave('actaFiniquito')
                ->whereDoesntHave('restitucion', fn ($r) => $r->whereIn('estado', [
                    EstadoAccionPersonal::REGISTRADA->value, EstadoAccionPersonal::NOTIFICADA->value,
                ])))
            ->with(['servidor:id,nombre,segundo_nombre,apellido,segundo_apellido,cedula', 'actaFiniquito', 'restitucion'])
            ->orderByDesc('fecha_efectiva')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MovimientoPersonal $m) => [
                'movimiento_id'   => $m->id,
                'codigo_registro' => $m->codigo_registro,
                'servidor'        => $m->servidor?->only(['id', 'nombre', 'segundo_nombre', 'apellido', 'segundo_apellido', 'cedula']),
                'causal'          => $m->subtipoEfectivo()?->value,
                'causal_etiqueta' => $m->subtipoEfectivo()?->etiqueta(),
                'fecha_efectiva'  => $m->fecha_efectiva?->toDateString(),
                'total_referencial' => $this->liquidacion($m)['total'] ?? null,
                'acta'            => $m->actaFiniquito,
                'reintegro'       => $m->restitucion ? [
                    'id'              => $m->restitucion->id,
                    'codigo_registro' => $m->restitucion->codigo_registro,
                    'estado'          => $m->restitucion->estado->value,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Registra o corrige el acta de finiquito de una terminación de obrero ya
     * registrada. El acta es posterior a la terminación y no antes de hoy.
     *
     * @param  array{numero: string, fecha: string, valor_pagado: float|string, observacion?: ?string}  $datos
     */
    public function guardarActa(MovimientoPersonal $terminacion, array $datos, ?UploadedFile $archivo, int $userId): ActaFiniquito
    {
        if ($terminacion->instrumento !== InstrumentoActo::DOCUMENTO_CT
            || ! $terminacion->cierraElVinculo()
            || ! in_array($terminacion->estado, [EstadoAccionPersonal::REGISTRADA, EstadoAccionPersonal::NOTIFICADA], true)
        ) {
            throw new ReglaNegocioException(
                'El acta de finiquito es de la terminación del contrato de un obrero, ya registrada.'
            );
        }

        $fecha = Carbon::parse($datos['fecha'])->toDateString();

        if ($terminacion->fecha_efectiva && $fecha < $terminacion->fecha_efectiva->toDateString()) {
            throw new ReglaNegocioException(
                'El acta de finiquito es posterior a la terminación, que rige desde el '
                    .$terminacion->fecha_efectiva->format('d/m/Y').'.'
            );
        }

        return DB::transaction(function () use ($terminacion, $datos, $archivo, $userId, $fecha) {
            $acta = ActaFiniquito::firstOrNew(['movimiento_personal_id' => $terminacion->id]);
            $anterior = $acta->documento_ruta;

            $acta->fill([
                'numero'       => trim($datos['numero']),
                'fecha'        => $fecha,
                'valor_pagado' => $datos['valor_pagado'],
                'observacion'  => filled($datos['observacion'] ?? null) ? trim($datos['observacion']) : null,
                'updated_by'   => $userId,
            ]);
            $acta->created_by ??= $userId;

            if ($archivo) {
                $ruta = $archivo->store("expediente/actas-finiquito/{$terminacion->id}", 'local');

                if (! $ruta) {
                    throw new ReglaNegocioException('No se pudo guardar el archivo. Inténtelo de nuevo.');
                }

                $acta->documento_ruta = $ruta;
                $acta->documento_nombre = $archivo->getClientOriginalName();
            }

            $acta->save();

            // El anterior se borra después de guardar el nuevo.
            if ($archivo && $anterior && $anterior !== $acta->documento_ruta) {
                Storage::disk('local')->delete($anterior);
            }

            return $acta->fresh();
        });
    }
}

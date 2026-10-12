<?php

namespace App\Services\Nomina;

use App\Contracts\Nomina\NominaServiceInterface;
use App\Enums\ConceptoNovedadNomina;
use App\Enums\EstadoContrato;
use App\Enums\EstadoDescuentoRecurrente;
use App\Enums\EstadoNomina;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Jobs\Nomina\EnviarRolPagoJob;
use App\Jobs\Nomina\GenerarHandoffErpJob;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\SalarioBasico;
use App\Models\Nomina\ConceptoNomina;
use App\Models\Nomina\DescuentoRecurrente;
use App\Models\Nomina\DetalleNomina;
use App\Models\Nomina\Nomina;
use App\Models\Nomina\RolPago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La nómina del SGTH (diseño de Acciones de Personal, 9.8; fase 7.3c).
 *
 * Hasta aquí pagaba a todo servidor activo la RMU de su puesto, el mes entero,
 * sin leer ningún acto. Ahora:
 *
 * - La base es el vínculo: cada contrato con relación de dependencia que toca
 *   el mes —sin Servicios Profesionales, que cobran honorarios contra
 *   factura— cuenta sus días en el mes de treinta, por tramos según la fecha
 *   en que rige cada cambio de remuneración (`RemuneracionDelVinculo`). Quien
 *   tuvo dos vínculos en el mes tiene un rol con los dos.
 * - Aplica las novedades del mes (`NovedadesDelMes`): los días sin
 *   remuneración, la subrogación y el encargo, la multa y las remuneraciones
 *   pendientes. Lo que se registró en el mes y rige desde antes va como
 *   retroactivo.
 *
 * Es **referencial** mientras dura la corrida en paralelo (`config/nomina`):
 * Financiero paga en su sistema. Las tasas del IESS, los décimos de la LOSEP,
 * el impuesto a la renta y los fondos de reserva siguen como estaban hasta que
 * Financiero responda.
 */
class NominaService implements NominaServiceInterface
{
    /** Lo que suman las novedades, por su concepto de nómina. */
    private const CONCEPTO_DE_LA_NOVEDAD = [
        ConceptoNovedadNomina::DIAS_SIN_REMUNERACION->value     => 'DIAS_SIN_REMUNERACION',
        ConceptoNovedadNomina::SUBROGACION->value               => 'SUBROGACION',
        ConceptoNovedadNomina::ENCARGO->value                   => 'ENCARGO',
        ConceptoNovedadNomina::MULTA->value                     => 'MULTA_DISCIPLINARIA',
        ConceptoNovedadNomina::REMUNERACIONES_PENDIENTES->value => 'REMUNERACIONES_PENDIENTES',
        // Lo que paga la salida (fase 7.4b): fuera de la base del IESS hasta
        // que Financiero diga lo contrario.
        ConceptoNovedadNomina::VACACIONES_NO_GOZADAS->value     => 'VACACIONES_PAGADAS',
        ConceptoNovedadNomina::INDEMNIZACION->value             => 'INDEMNIZACION',
    ];

    public function __construct(
        private readonly NovedadesDelMes $novedades,
        private readonly RemuneracionDelVinculo $remuneracion,
    ) {
    }

    /**
     * Calcula los ingresos, descuentos y totales del mes, en borrador. Se puede
     * recalcular mientras no se cierre: nada de lo que hace se acumula.
     */
    public function calcularNomina(string $periodo): Nomina
    {
        $mes = MesDeNomina::de($periodo);

        return DB::transaction(function () use ($mes) {
            $nomina = Nomina::firstOrCreate(
                ['periodo' => $mes->periodo],
                [
                    'fecha_inicio' => $mes->inicio->toDateString(),
                    'fecha_fin'    => $mes->fin->toDateString(),
                    'estado'       => EstadoNomina::BORRADOR,
                    'referencial'  => (bool) config('nomina.referencial', true),
                ]
            );

            if (in_array($nomina->estado, [EstadoNomina::CERRADA, EstadoNomina::PAGADA, EstadoNomina::CONTABILIZADA], true)) {
                throw new ReglaNegocioException("La nómina de {$mes->etiqueta()} ya está cerrada y no se recalcula.");
            }

            $nomina->update(['estado' => EstadoNomina::EN_PROCESO]);

            DetalleNomina::where('nomina_id', $nomina->id)->delete();
            RolPago::where('nomina_id', $nomina->id)->forceDelete();

            $conceptos = ConceptoNomina::where('activo', true)->get()->keyBy('codigo');
            $novedades = $this->novedades->de($mes)->groupBy('servidor_id');
            $this->remuneracion->olvidar();
            $basico = SalarioBasico::delAnio($mes->inicio->year);

            $totales = ['ingresos' => 0.0, 'descuentos' => 0.0, 'neto' => 0.0];

            foreach ($this->vinculosDelMes($mes) as $servidorId => $vinculos) {
                $rol = $this->rolDelServidor($nomina, $mes, $vinculos, $novedades->get($servidorId, collect()), $conceptos, $basico);

                if ($rol) {
                    $totales['ingresos'] += $rol->total_ingresos;
                    $totales['descuentos'] += $rol->total_descuentos;
                    $totales['neto'] += $rol->total_neto;
                }
            }

            $nomina->update([
                'total_ingresos'   => round($totales['ingresos'], 2),
                'total_descuentos' => round($totales['descuentos'], 2),
                'total_neto'       => round($totales['neto'], 2),
                // Vuelve a borrador, esperando revisión.
                'estado'           => EstadoNomina::BORRADOR,
            ]);

            return $nomina;
        });
    }

    /**
     * Cierra la nómina: congela los valores y cuenta como pagadas las cuotas de
     * los préstamos que descontó. La referencial no sale al ERP ni a los
     * servidores: se cierra para compararla con la de Financiero.
     */
    public function cerrarNomina(int $nominaId, int $userId): Nomina
    {
        $nomina = DB::transaction(function () use ($nominaId, $userId) {
            $nomina = Nomina::lockForUpdate()->findOrFail($nominaId);

            if (in_array($nomina->estado, [EstadoNomina::CERRADA, EstadoNomina::PAGADA, EstadoNomina::CONTABILIZADA], true)) {
                throw new ReglaNegocioException('La nómina ya está cerrada.');
            }

            $nomina->update([
                'estado'      => EstadoNomina::CERRADA,
                'cerrado_por' => $userId,
                'cerrado_en'  => now(),
            ]);

            // La cuota se paga al cerrar, una vez, y no al calcular el borrador.
            $cuotas = DetalleNomina::where('nomina_id', $nomina->id)
                ->whereNotNull('descuento_recurrente_id')
                ->pluck('descuento_recurrente_id');

            foreach (DescuentoRecurrente::whereIn('id', $cuotas)->lockForUpdate()->get() as $descuento) {
                $pagadas = $descuento->numero_cuotas_pagadas + 1;
                $descuento->update([
                    'numero_cuotas_pagadas' => $pagadas,
                    'estado'                => $pagadas >= $descuento->numero_cuotas_total
                        ? EstadoDescuentoRecurrente::COMPLETADO
                        : $descuento->estado,
                ]);
            }

            return $nomina;
        });

        if (! $nomina->referencial) {
            GenerarHandoffErpJob::dispatch($nomina->id);

            foreach ($nomina->rolesPago as $rol) {
                EnviarRolPagoJob::dispatch($rol->id);
            }
        }

        return $nomina->fresh();
    }

    /**
     * Los vínculos que tocan el mes, por servidor: los de relación de
     * dependencia, sin Servicios Profesionales.
     *
     * @return Collection<int, Collection<int, ContratoServidor>>
     */
    private function vinculosDelMes(MesDeNomina $mes): Collection
    {
        return ContratoServidor::query()
            ->where('tipo_nombramiento', '!=', TipoNombramiento::SERVICIOS_PROFESIONALES->value)
            ->where('estado', '!=', EstadoContrato::CANCELADO->value)
            ->whereDate('fecha_inicio', '<=', $mes->fin->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $mes->inicio->toDateString()))
            ->with('servidor:id,regimen_laboral')
            ->orderBy('servidor_id')
            ->orderBy('fecha_inicio')
            ->get()
            ->groupBy('servidor_id');
    }

    /**
     * El rol de un servidor en el mes.
     *
     * @param  Collection<int, ContratoServidor>  $vinculos
     * @param  Collection<int, array<string, mixed>>  $novedades
     * @param  Collection<string, ConceptoNomina>  $conceptos
     */
    private function rolDelServidor(
        Nomina $nomina,
        MesDeNomina $mes,
        Collection $vinculos,
        Collection $novedades,
        Collection $conceptos,
        ?float $basico,
    ): ?RolPago {
        $servidorId = (int) $vinculos->first()->servidor_id;
        $lineas = [];
        $sueldo = 0.0;
        $dias = 0;
        $remuneracion = null;
        $sinRemuneracion = false;

        foreach ($vinculos as $vinculo) {
            foreach ($this->remuneracion->tramos($vinculo, $mes) as $tramo) {
                $dias += $tramo['dias'];

                if ($tramo['remuneracion'] === null) {
                    $sinRemuneracion = true;

                    continue;
                }

                $sueldo += $tramo['remuneracion'] * $tramo['dias'] / MesDeNomina::DIAS;
                $remuneracion = $tramo['remuneracion'];
            }
        }

        $dias = min($dias, MesDeNomina::DIAS);
        $this->linea($lineas, 'SUELDO_BASE', $sueldo, $sinRemuneracion
            ? "{$dias} días; un vínculo no tiene remuneración guardada y no se pagó."
            : "{$dias} días.");

        $retroactivo = (float) $vinculos->sum(fn (ContratoServidor $v) => $this->remuneracion->retroactivo($v, $mes));
        $this->linea($lineas, 'RETROACTIVO', $retroactivo, 'Cambios registrados este mes que rigen desde antes.');

        foreach (self::CONCEPTO_DE_LA_NOVEDAD as $concepto => $codigo) {
            $deEste = $novedades->filter(fn (array $n) => $n['concepto']->value === $concepto && $n['monto'] !== null);
            $this->linea($lineas, $codigo, abs((float) $deEste->sum('monto')), $deEste->pluck('acto')->filter()->unique()->implode(', ') ?: null);
        }

        $aportable = $this->suma($lineas, ['SUELDO_BASE', 'RETROACTIVO', 'SUBROGACION', 'ENCARGO', 'REMUNERACIONES_PENDIENTES'])
            - $this->suma($lineas, ['DIAS_SIN_REMUNERACION']);

        // Los décimos, mensualizados, siguen siendo solo del Código del Trabajo
        // hasta que Financiero diga cómo los cobra la LOSEP.
        $esObrero = $vinculos->contains(fn (ContratoServidor $v) => $v->tipo_nombramiento === TipoNombramiento::CODIGO_TRABAJO);
        if ($esObrero) {
            $this->linea($lineas, 'DECIMO_TERCERO', $aportable / 12, null);
            $this->linea($lineas, 'DECIMO_CUARTO', $basico !== null ? $basico / 12 * $dias / MesDeNomina::DIAS : 0.0,
                $basico === null ? "Sin el salario básico de {$mes->inicio->year}: cárguelo en el contrato colectivo." : null);
        }

        $porcentajeIess = (float) ($conceptos->get('IESS_PERSONAL')?->porcentaje ?? 9.45);
        $this->linea($lineas, 'IESS_PERSONAL', max($aportable, 0) * $porcentajeIess / 100, null);

        foreach ($this->cuotasDelMes($servidorId, $mes) as $descuento) {
            $this->linea($lineas, null, (float) $descuento->valor_cuota,
                'Cuota '.($descuento->numero_cuotas_pagadas + 1)." de {$descuento->numero_cuotas_total}"
                    .($descuento->referencia_externa ? " · Ref: {$descuento->referencia_externa}" : ''),
                $descuento);
        }

        if ($sueldo <= 0 && $novedades->isEmpty()) {
            return null;
        }

        $ingresos = 0.0;
        $descuentos = 0.0;

        foreach ($lineas as $linea) {
            $concepto = $linea['descuento']?->concepto_nomina_id
                ? $conceptos->firstWhere('id', $linea['descuento']->concepto_nomina_id) ?? ConceptoNomina::find($linea['descuento']->concepto_nomina_id)
                : $conceptos->get($linea['codigo']);

            if (! $concepto) {
                continue;
            }

            DetalleNomina::create([
                'nomina_id'               => $nomina->id,
                'servidor_id'             => $servidorId,
                'concepto_nomina_id'      => $concepto->id,
                'descuento_recurrente_id' => $linea['descuento']?->id,
                'valor'                   => $linea['valor'],
                'observacion'             => $linea['observacion'],
            ]);

            if ($concepto->tipo->value === 'ingreso') {
                $ingresos += $linea['valor'];
            } elseif ($concepto->tipo->value === 'descuento') {
                $descuentos += $linea['valor'];
            }
        }

        return RolPago::create([
            'nomina_id'        => $nomina->id,
            'servidor_id'      => $servidorId,
            'dias'             => $dias,
            'remuneracion'     => $remuneracion,
            'total_ingresos'   => round($ingresos, 2),
            'total_descuentos' => round($descuentos, 2),
            'total_neto'       => round($ingresos - $descuentos, 2),
        ]);
    }

    /**
     * Las cuotas de los préstamos que tocan el mes: por las fechas del mes, no
     * por las de hoy, y sin las que ya se pagaron todas.
     *
     * @return Collection<int, DescuentoRecurrente>
     */
    private function cuotasDelMes(int $servidorId, MesDeNomina $mes): Collection
    {
        return DescuentoRecurrente::where('servidor_id', $servidorId)
            ->where('estado', EstadoDescuentoRecurrente::ACTIVO)
            ->whereDate('fecha_inicio', '<=', $mes->fin->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $mes->inicio->toDateString()))
            ->whereColumn('numero_cuotas_pagadas', '<', 'numero_cuotas_total')
            ->orderBy('id')
            ->get();
    }

    /** @param  list<array{codigo: ?string, valor: float, observacion: ?string, descuento: ?DescuentoRecurrente}>  $lineas */
    private function linea(array &$lineas, ?string $codigo, float $valor, ?string $observacion, ?DescuentoRecurrente $descuento = null): void
    {
        $valor = round($valor, 2);

        if ($valor <= 0) {
            return;
        }

        $lineas[] = ['codigo' => $codigo, 'valor' => $valor, 'observacion' => $observacion, 'descuento' => $descuento];
    }

    /** @param  list<array{codigo: ?string, valor: float}>  $lineas */
    private function suma(array $lineas, array $codigos): float
    {
        return (float) collect($lineas)->whereIn('codigo', $codigos)->sum('valor');
    }
}

<?php

namespace App\Services\Asistencia;

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuántos días de vacaciones puede acumular un servidor, y qué pasa con los
 * que exceden.
 *
 * - LOSEP, art. 29: hasta 60 días.
 * - Código del Trabajo, art. 75: hasta tres años de vacaciones, es decir, tres
 *   veces lo que el servidor genera al año.
 *
 * Hasta ahora el tope no se aplicaba en ningún sitio. `calcularCifras()`
 * recortaba a 60 el arrastre que se mostraba como acumulado, pero el saldo real
 * —el que se valida al pedir vacaciones— seguía creciendo, y el recorte solo
 * escondía el excedente de la vista de quien lo tenía que gestionar.
 *
 * Nada vence solo. El sistema lista a quien está cerca o por encima de su tope,
 * y Talento Humano decide vencer el excedente de cada servidor: es quitarle
 * días a una persona, y eso lo hace alguien con nombre, que queda en la
 * bitácora.
 */
class TopeAcumulacionService
{
    public const TOPE_LOSEP = 60.0;

    public const ANIOS_CODIGO_TRABAJO = 3;

    /** Desde qué fracción del tope se avisa: 45 de 60 en LOSEP. */
    public const UMBRAL_ALERTA = 0.75;

    public function __construct(private PeriodoVacacionService $periodos) {}

    /**
     * El tope del servidor, o `null` si no tiene: servicios profesionales no
     * genera vacaciones, y en el Código del Trabajo sin períodos no hay de
     * dónde saber cuánto genera al año.
     */
    public function topePara(Servidor $servidor): ?float
    {
        return match ($this->regimen($servidor->regimen_laboral)) {
            RegimenLaboral::LOSEP, null             => self::TOPE_LOSEP,
            RegimenLaboral::CODIGO_TRABAJO          => $this->topeCodigoTrabajo(
                PeriodoVacacion::where('servidor_id', $servidor->id)
                    ->orderByDesc('anio')
                    ->value('dias_generados')
            ),
            RegimenLaboral::SERVICIOS_PROFESIONALES => null,
        };
    }

    /**
     * Saldo, tope, excedente y si toca avisar. Lo usa el resumen del servidor.
     *
     * @return array{saldo: float, tope: float|null, excedente: float, alerta: bool}
     */
    public function estado(Servidor $servidor): array
    {
        $saldo = $this->periodos->saldoTotal($servidor->id);
        $tope  = $this->topePara($servidor);

        return [
            'saldo'     => $saldo,
            'tope'      => $tope,
            'excedente' => $this->excedente($saldo, $tope),
            'alerta'    => $tope !== null && $saldo >= $tope * self::UMBRAL_ALERTA,
        ];
    }

    /**
     * Servidores activos que llegan al umbral de alerta o lo pasan.
     *
     * Una consulta agregada para toda la plantilla. El KPI del dashboard hacía
     * lo mismo servidor por servidor, con varias consultas cada uno.
     *
     * @return Collection<int, array{servidor_id: int, nombre: string, cedula: string, unidad: string|null, regimen: string, saldo: float, tope: float, excedente: float}>
     */
    public function enSeguimiento(bool $soloExcedidos = false): Collection
    {
        $saldos = DB::table('periodos_vacaciones as p')
            ->join('servidores as s', 's.id', '=', 'p.servidor_id')
            ->where('s.estado', true)
            ->where('p.estado', 'abierto')
            ->whereNotIn('s.regimen_laboral', RegimenLaboral::valoresSinVacaciones())
            ->whereNull('s.deleted_at')
            ->groupBy('p.servidor_id', 's.regimen_laboral')
            ->selectRaw('p.servidor_id, s.regimen_laboral, SUM(p.dias_saldo) AS saldo')
            ->get();

        // Lo que genera al año cada servidor del Código del Trabajo: el
        // período más reciente.
        $idsCodigoTrabajo = $saldos
            ->where('regimen_laboral', RegimenLaboral::CODIGO_TRABAJO->value)
            ->pluck('servidor_id');

        $generadoPorAnio = DB::table('periodos_vacaciones as p')
            ->whereIn('p.servidor_id', $idsCodigoTrabajo)
            ->whereRaw('p.anio = (SELECT MAX(anio) FROM periodos_vacaciones WHERE servidor_id = p.servidor_id)')
            ->pluck('p.dias_generados', 'p.servidor_id');

        $filas = $saldos
            ->map(function ($fila) use ($generadoPorAnio) {
                $saldo = (float) $fila->saldo;
                $tope  = $this->regimen($fila->regimen_laboral) === RegimenLaboral::CODIGO_TRABAJO
                    ? $this->topeCodigoTrabajo($generadoPorAnio[$fila->servidor_id] ?? null)
                    : self::TOPE_LOSEP;

                return [
                    'servidor_id' => (int) $fila->servidor_id,
                    'regimen'     => (string) $fila->regimen_laboral,
                    'saldo'       => $saldo,
                    'tope'        => $tope,
                    'excedente'   => $this->excedente($saldo, $tope),
                ];
            })
            ->filter(fn (array $f) => $f['tope'] !== null && (
                $soloExcedidos
                    ? $f['excedente'] > 0
                    : $f['saldo'] >= $f['tope'] * self::UMBRAL_ALERTA
            ));

        $personas = DB::table('servidores as s')
            ->leftJoin('unidades_administrativas as u', 'u.id', '=', 's.unidad_administrativa_id')
            ->whereIn('s.id', $filas->pluck('servidor_id'))
            ->get(['s.id', 's.nombre', 's.apellido', 's.cedula', 'u.nombre as unidad'])
            ->keyBy('id');

        return $filas
            ->map(function (array $f) use ($personas) {
                $persona = $personas[$f['servidor_id']] ?? null;

                return [
                    'servidor_id' => $f['servidor_id'],
                    'nombre'      => trim(($persona->apellido ?? '').' '.($persona->nombre ?? '')),
                    'cedula'      => (string) ($persona->cedula ?? ''),
                    'unidad'      => $persona->unidad ?? null,
                    'regimen'     => $f['regimen'],
                    'saldo'       => round($f['saldo'], 2),
                    'tope'        => $f['tope'],
                    'excedente'   => $f['excedente'],
                ];
            })
            ->sortByDesc(fn (array $f) => [$f['excedente'], $f['saldo']])
            ->values();
    }

    /**
     * Vence el excedente de un servidor sobre su tope.
     *
     * Se toma de los períodos más antiguos, igual que al gozar: son los que
     * llevan más tiempo acumulándose. Los días van a `dias_vencidos` y no a
     * `dias_utilizados` —no se gozaron—, así que ni el resumen los cuenta como
     * vacaciones ni una regeneración o una anulación los devuelve.
     *
     * @return array{dias_vencidos: float, saldo_antes: float, saldo_despues: float, tope: float, tramos: list<array{anio: int, dias: float}>}
     */
    public function vencerExcedente(Servidor $servidor, User $usuario): array
    {
        return DB::transaction(function () use ($servidor, $usuario) {
            $abiertos = PeriodoVacacion::where('servidor_id', $servidor->id)
                ->where('estado', 'abierto')
                ->orderBy('anio')
                ->lockForUpdate()
                ->get();

            $tope = $this->topePara($servidor);

            if ($tope === null) {
                throw new ReglaNegocioException(
                    'Este servidor no tiene tope de acumulación: su régimen no genera vacaciones '
                    .'o todavía no tiene períodos.'
                );
            }

            $saldo     = (float) $abiertos->sum('dias_saldo');
            $excedente = $this->excedente($saldo, $tope);

            if ($excedente <= 0) {
                throw new ReglaNegocioException(sprintf(
                    'El servidor tiene %s días y su tope es de %s: no hay excedente que vencer.',
                    number_format($saldo, 2), number_format($tope, 2)
                ));
            }

            $restante = $excedente;
            $tramos   = [];

            foreach ($abiertos as $periodo) {
                if ($restante <= 0) {
                    break;
                }

                $vence = round(min((float) $periodo->dias_saldo, $restante), 2);

                if ($vence <= 0) {
                    continue;
                }

                $periodo->dias_vencidos = (float) $periodo->dias_vencidos + $vence;
                $periodo->recalcularSaldo();
                $periodo->save();

                $tramos[]  = ['anio' => (int) $periodo->anio, 'dias' => $vence];
                $restante  = round($restante - $vence, 2);
            }

            $this->periodos->recalcularAcumulados($servidor->id);

            $resultado = [
                'dias_vencidos' => $excedente,
                'saldo_antes'   => round($saldo, 2),
                'saldo_despues' => round($saldo - $excedente, 2),
                'tope'          => $tope,
                'tramos'        => $tramos,
            ];

            activity('periodos-vacaciones')
                ->performedOn($servidor)
                ->causedBy($usuario)
                ->withProperties($resultado + ['regimen' => $this->regimen($servidor->regimen_laboral)?->value])
                ->log('Vencimiento de días sobre el tope de acumulación');

            return $resultado;
        });
    }

    private function topeCodigoTrabajo(mixed $diasPorAnio): ?float
    {
        return $diasPorAnio === null
            ? null
            : self::ANIOS_CODIGO_TRABAJO * (float) $diasPorAnio;
    }

    private function excedente(float $saldo, ?float $tope): float
    {
        return $tope === null ? 0.0 : round(max(0.0, $saldo - $tope), 2);
    }

    private function regimen(mixed $valor): ?RegimenLaboral
    {
        return $valor instanceof RegimenLaboral
            ? $valor
            : RegimenLaboral::tryFrom((string) ($valor ?? 'losep'));
    }
}

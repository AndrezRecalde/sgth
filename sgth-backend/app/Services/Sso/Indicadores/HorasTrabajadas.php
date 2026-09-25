<?php

namespace App\Services\Sso\Indicadores;

use App\Services\Sso\PeriodoSso;
use Illuminate\Support\Collection;

/**
 * El denominador de los índices reactivos CD 513: cuántas horas trabajadas
 * corresponden a un período, y de dónde se tomaron.
 *
 * Existe como clase aparte porque la decisión no es obvia y conviene poder
 * probarla sin base de datos. `horas_trabajadas_periodo` guarda el período como
 * cadena ('2026' para un año, '2026-07' para un mes) y la unidad como columna
 * nullable ('total institucional'), mientras las lesiones del numerador se
 * cuentan por rango de fechas y —sin unidad— sobre toda la institución. Buscar
 * la fila con una igualdad exacta en los dos ejes dejaba el índice ANUAL, que
 * es el que se informa al IESS, sin denominador: respondía «no hay horas
 * registradas» con los doce meses cargados.
 *
 * Se resuelve en dos ejes, en este orden, y lo decidido viaja hasta la pantalla
 * (`detalle()`), porque dos consultas del mismo período pueden legítimamente
 * apoyarse en cifras distintas y quien lee el índice tiene que saber en cuál.
 */
final class HorasTrabajadas
{
    /** El período pedido tenía su propia fila cargada. */
    public const ORIGEN_EXACTO = 'periodo_exacto';

    /** No había fila del año, así que se sumaron sus meses. */
    public const ORIGEN_MESES = 'suma_de_meses';

    /** Las filas sin unidad: lo que el modal de carga llama «total institucional». */
    public const ALCANCE_INSTITUCIONAL = 'institucional';

    /** Las filas de la unidad consultada. */
    public const ALCANCE_UNIDAD = 'unidad';

    /** No hay total institucional cargado: se suman todas las unidades. */
    public const ALCANCE_SUMA_UNIDADES = 'suma_de_unidades';

    private function __construct(
        public readonly int $horas,
        public readonly ?string $origen,
        public readonly ?string $alcance,
        public readonly int $meses,
        public readonly int $unidades,
    ) {}

    public static function sinDatos(): self
    {
        return new self(0, null, null, 0, 0);
    }

    /**
     * Reduce las filas candidatas de un período —la del período pedido y, si es
     * un año, las de sus meses— a un único denominador.
     *
     * @param Collection<int, mixed> $filas con 'periodo', 'unidad_administrativa_id' y 'total_horas'
     */
    public static function desde(Collection $filas, string $periodo, ?int $unidadAdministrativaId): self
    {
        if ($filas->isEmpty()) {
            return self::sinDatos();
        }

        // ── Eje alcance ────────────────────────────────────────────────
        // Con unidad, solo las filas de esa unidad: el filtro se repite aquí
        // —la consulta ya lo aplica— para que la clase no dependa de que quien
        // la llama lo recuerde. Sin unidad manda el total institucional; si
        // nadie lo cargó, se suman las unidades, porque las lesiones del
        // numerador son de toda la institución y un denominador parcial infla
        // el índice.
        if ($unidadAdministrativaId !== null) {
            $alcance = self::ALCANCE_UNIDAD;
            $filas = $filas->where('unidad_administrativa_id', $unidadAdministrativaId);

            if ($filas->isEmpty()) {
                return self::sinDatos();
            }
        } elseif ($filas->whereNull('unidad_administrativa_id')->isNotEmpty()) {
            $alcance = self::ALCANCE_INSTITUCIONAL;
            $filas = $filas->whereNull('unidad_administrativa_id');
        } else {
            $alcance = self::ALCANCE_SUMA_UNIDADES;
        }

        // ── Eje período ────────────────────────────────────────────────
        // Manda la fila del período pedido. Si no existe y el período es un
        // año, se suman sus meses. Al revés no: repartir un total anual entre
        // doce meses sería inventar el dato.
        $exactas = $filas->where('periodo', $periodo);

        if ($exactas->isNotEmpty()) {
            $filas = $exactas;
            $origen = self::ORIGEN_EXACTO;
        } elseif (PeriodoSso::esAnio($periodo)) {
            $origen = self::ORIGEN_MESES;
        } else {
            return self::sinDatos();
        }

        return new self(
            horas: (int) $filas->sum('total_horas'),
            origen: $origen,
            alcance: $alcance,
            meses: $filas->pluck('periodo')->unique()->count(),
            unidades: $filas->pluck('unidad_administrativa_id')->unique()->count(),
        );
    }

    public function hay(): bool
    {
        return $this->horas > 0;
    }

    /** Una línea para la pantalla: de dónde salió el denominador del índice. */
    public function detalle(): ?string
    {
        if (! $this->hay()) {
            return null;
        }

        $periodo = $this->origen === self::ORIGEN_MESES
            ? "Horas tomadas de la suma de {$this->meses} meses cargados"
            : 'Horas tomadas del período cargado';

        $alcance = match ($this->alcance) {
            self::ALCANCE_UNIDAD => 'con alcance de la unidad consultada',
            self::ALCANCE_SUMA_UNIDADES => "sumando {$this->unidades} unidades, porque no hay un total institucional cargado",
            default => 'con alcance institucional',
        };

        return "{$periodo}, {$alcance}.";
    }

}

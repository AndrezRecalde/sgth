<?php

namespace App\Exports\MasaSalarial;

use App\Enums\RegimenLaboral;
use App\Enums\TipoNombramiento;
use App\Services\Estructura\MasaSalarialService;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * El Excel de la masa salarial referencial (docs/diseno-masa-salarial.md,
 * 5.4): el resumen con el aviso y la fecha de corte, la matriz de unidad por
 * régimen y el detalle de cada vínculo. Lo arma con la misma respuesta que ve
 * la pantalla (`MasaSalarialService::al`), así que no puede decir otra cosa.
 */
final class MasaSalarialExport implements WithMultipleSheets
{
    private const SITUACIONES = [
        MasaSalarialService::SUMA                         => 'Suma',
        MasaSalarialService::SIN_REMUNERACION_EN_LA_FECHA => 'Sin remuneración en la fecha',
        MasaSalarialService::SIN_REMUNERACION_REGISTRADA  => 'Sin remuneración registrada',
    ];

    private const CIFRAS = ['Personas', 'Mensual', 'Anual (× 12)', '% del total', 'Promedio'];

    /**
     * Una fila en blanco. Con `[]`, Laravel Excel no escribe la fila y todo
     * lo de abajo sube una: las negritas, que van por número de fila, caían
     * en la fila de al lado.
     */
    private const EN_BLANCO = [''];

    /** @param  array<string, mixed>  $datos  lo que devuelve `MasaSalarialService::al` */
    public function __construct(private readonly array $datos)
    {
    }

    public function sheets(): array
    {
        return [$this->resumen(), $this->unidadPorRegimen(), $this->detalle()];
    }

    public static function nombreDelArchivo(string $corte): string
    {
        return "masa_salarial_{$corte}.xlsx";
    }

    // ── Resumen ─────────────────────────────────────────────────────

    private function resumen(): HojaMasaSalarial
    {
        $filas = $this->encabezado('resumen');
        $negritas = [];
        $titulo = function (string $texto, array $cabeceras) use (&$filas, &$negritas) {
            $filas[] = [$texto];
            $negritas[] = count($filas);
            $filas[] = $cabeceras;
            $negritas[] = count($filas);
        };
        $totales = $this->datos['totales'];

        $titulo('Totales', ['Concepto', ...self::CIFRAS]);
        $filas[] = $this->cifras('Relación de dependencia', $totales['dependencia']);
        $filas[] = $this->cifras('Honorarios de Servicios Profesionales', $totales['servicios_profesionales']);
        $filas[] = $this->cifras('Total general', $totales['general']);
        $negritas[] = count($filas);

        foreach (['tipo_nombramiento' => 'Tipo de nombramiento', 'regimen' => 'Régimen'] as $vista => $nombre) {
            $filas[] = self::EN_BLANCO;
            $titulo("Por {$this->minuscula($nombre)}", [$nombre, ...self::CIFRAS]);

            foreach ($this->datos['vistas'][$vista] as $caso) {
                $filas[] = $this->cifras($caso['etiqueta'], $caso);
            }

            $filas[] = $this->cifras('Total', $totales['general']);
            $negritas[] = count($filas);
        }

        $aparte = $this->datos['aparte'];
        $filas[] = self::EN_BLANCO;
        $titulo('Fuera del total', ['Concepto', 'Personas o casos', 'Mensual']);
        $filas[] = [
            'Sin remuneración en la fecha: licencia, comisión o suspensión',
            $aparte['sin_remuneracion_en_la_fecha']['personas'],
            $aparte['sin_remuneracion_en_la_fecha']['mensual'],
        ];
        $filas[] = ['Diferencia de subrogaciones y encargos', $aparte['subrogaciones']['casos'], $aparte['subrogaciones']['mensual']];

        $porCompletar = $this->datos['por_completar'];
        $filas[] = self::EN_BLANCO;
        $titulo('Datos por completar', ['Concepto', 'Cantidad']);
        $filas[] = ['Vínculos sin remuneración registrada', $porCompletar['vinculos_sin_remuneracion']];
        $filas[] = ['Vínculos sin unidad', $porCompletar['vinculos_sin_unidad']];
        $filas[] = [
            'Servidores activos sin vínculo vigente',
            $porCompletar['servidores_sin_vinculo'] === null
                ? 'Solo se sabe para hoy, sin filtro de régimen ni de tipo'
                : count($porCompletar['servidores_sin_vinculo']),
        ];

        return new HojaMasaSalarial('Resumen', $filas, $negritas, dinero: ['C', 'D', 'F'], porcentaje: ['E'], encabezado: 3);
    }

    /** @param  array{personas: int, mensual: float, anual: float, porcentaje: float, promedio: float}  $bloque */
    private function cifras(string $etiqueta, array $bloque): array
    {
        return [$etiqueta, $bloque['personas'], $bloque['mensual'], $bloque['anual'], $bloque['porcentaje'], $bloque['promedio']];
    }

    // ── Unidad por régimen ──────────────────────────────────────────

    /**
     * Una fila por unidad, en el orden del árbol y con sangría, y una columna
     * por régimen. Cada celda suma lo que cuelga de la unidad, como en la
     * pantalla: las filas que la siguen con más profundidad.
     */
    private function unidadPorRegimen(): HojaMasaSalarial
    {
        $regimenes = RegimenLaboral::cases();
        $filas = $this->encabezado('unidad por régimen');
        $filas[] = ['Unidad administrativa', ...array_map(fn (RegimenLaboral $r) => $r->etiqueta(), $regimenes), 'Total', 'Personas'];
        $negritas = [count($filas)];

        // En centavos mientras se suma, para que la fila dé su total exacto.
        $centavos = [];
        foreach ($this->datos['detalle'] as $vinculo) {
            if ($vinculo['situacion'] === MasaSalarialService::SUMA && $vinculo['unidad_id'] !== null) {
                $centavos[$vinculo['unidad_id']][$vinculo['regimen']] = ($centavos[$vinculo['unidad_id']][$vinculo['regimen']] ?? 0)
                    + (int) round($vinculo['remuneracion'] * 100);
            }
        }

        $unidades = $this->datos['vistas']['unidad'];

        foreach ($unidades as $i => $unidad) {
            $ids = [$unidad['id']];
            for ($j = $i + 1; $j < count($unidades) && $unidades[$j]['profundidad'] > $unidad['profundidad']; $j++) {
                $ids[] = $unidades[$j]['id'];
            }

            $filas[] = [
                str_repeat('    ', $unidad['profundidad']).$unidad['nombre'],
                ...array_map(
                    fn (RegimenLaboral $r) => round(array_sum(array_map(fn (int $id) => $centavos[$id][$r->value] ?? 0, $ids)) / 100, 2),
                    $regimenes,
                ),
                $unidad['mensual'],
                $unidad['personas'],
            ];

            if ($unidad['profundidad'] === 0) {
                $negritas[] = count($filas);
            }
        }

        return new HojaMasaSalarial('Unidad por régimen', $filas, $negritas, dinero: ['B', 'C', 'D', 'E'], encabezado: 3);
    }

    // ── Detalle ─────────────────────────────────────────────────────

    /** Un vínculo por fila, con las cabeceras arriba para poder filtrar. */
    private function detalle(): HojaMasaSalarial
    {
        $filas = [['Cédula', 'Servidor', 'Tipo de nombramiento', 'Régimen', 'Unidad', 'Puesto', 'Grado', 'Remuneración', 'Situación']];

        foreach ($this->datos['detalle'] as $v) {
            $filas[] = [
                $v['cedula'], $v['servidor'], $v['tipo_nombramiento_etiqueta'], $v['regimen_etiqueta'], $v['unidad'],
                $v['puesto'], $v['grado'], $v['remuneracion'], self::SITUACIONES[$v['situacion']] ?? $v['situacion'],
            ];
        }

        return new HojaMasaSalarial('Detalle', $filas, [1], dinero: ['H'], texto: ['A']);
    }

    // ── El encabezado ───────────────────────────────────────────────

    /** El título, el aviso y los filtros, y una fila en blanco. */
    private function encabezado(string $que): array
    {
        $corte = CarbonImmutable::parse($this->datos['corte'])->format('d/m/Y');

        return [
            ["Masa salarial referencial al {$corte}: {$que}"],
            [$this->datos['aviso']],
            [$this->filtros()],
            self::EN_BLANCO,
        ];
    }

    private function filtros(): string
    {
        $filtros = $this->datos['filtros'];
        $partes = array_filter([
            $filtros['regimen'] ? 'régimen '.RegimenLaboral::from($filtros['regimen'])->etiqueta() : null,
            $filtros['tipo_nombramiento'] ? TipoNombramiento::from($filtros['tipo_nombramiento'])->etiqueta() : null,
            $filtros['unidad'] ? "{$filtros['unidad']}, con lo que cuelga de ella" : null,
        ]);

        return $partes ? 'Solo: '.implode(' · ', $partes).'.' : 'Toda la institución, sin filtros.';
    }

    private function minuscula(string $texto): string
    {
        return mb_strtolower(mb_substr($texto, 0, 1)).mb_substr($texto, 1);
    }
}

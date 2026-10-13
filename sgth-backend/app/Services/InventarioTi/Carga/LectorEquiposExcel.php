<?php

namespace App\Services\InventarioTi\Carga;

use App\Exceptions\ReglaNegocioException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Lee la pestaña «Equipos» del Excel de TIC (docs/diseno-subsistema-tic.md,
 * 2.1): la tabla `Computadoras`, con dos filas de encabezado.
 *
 * Las columnas se leen por posición y no por nombre: hay dos que se llaman
 * «SOPORTE» (una con punto) y dos «Memorando Nº 0xx…». Antes de leer se
 * comprueba que las columnas clave sigan donde estaban; si alguien movió una,
 * la carga se detiene en vez de poner la serie en el modelo.
 */
final class LectorEquiposExcel
{
    private const HOJA = 'Equipos';

    /** Columna (0 = A) => campo. */
    private const COLUMNAS = [
        1 => 'piso', 2 => 'anterior', 4 => 'actual', 5 => 'asignado', 6 => 'area', 8 => 'tipo', 9 => 'subtipo',
        10 => 'marca', 11 => 'modelo', 12 => 'serie', 13 => 'fecha_compra',
        14 => 'soporte_mantenimiento_2025', 15 => 'tecnico_mantenimiento_2025',
        16 => 'nombre_red', 17 => 'usuario_dominio', 18 => 'vigencia', 19 => 'custodia_administrativo',
        20 => 'tipo_disco', 21 => 'soporte_ssd', 22 => 'soporte_traspaso', 23 => 'memorando_traspaso',
        24 => 'usuario_anterior', 25 => 'soporte_baja', 26 => 'memorando_baja',
        27 => 'soporte_terremoto', 28 => 'tecnico_terremoto', 29 => 'observacion', 30 => 'novedades_dominio',
        31 => 'estado_equipo',
    ];

    /** Lo que tiene que decir el encabezado en las columnas que sostienen la carga. */
    private const ENCABEZADOS = [1 => 'PISO', 4 => 'ACTUAL', 5 => 'ASIGNADO', 12 => 'SERIE', 13 => 'FECHA DE COMPRA'];

    /**
     * @return list<array<string, mixed>> cada fila con `fila` (su número en el Excel)
     */
    public function leer(string $ruta): array
    {
        if (! is_file($ruta)) {
            throw new ReglaNegocioException("No existe el archivo «{$ruta}».");
        }

        $lector = IOFactory::createReaderForFile($ruta);
        $lector->setReadDataOnly(true);
        $lector->setLoadSheetsOnly([self::HOJA]);
        $hoja = $lector->load($ruta)->getSheetByName(self::HOJA)
            ?? throw new ReglaNegocioException('El Excel no tiene la pestaña «Equipos».');

        $celdas = $hoja->toArray(null, false, false, false);
        $encabezado = $this->filaDeEncabezado($celdas);

        $filas = [];
        foreach (array_slice($celdas, $encabezado + 1, null, true) as $indice => $celda) {
            $fila = ['fila' => $indice + 1];
            foreach (self::COLUMNAS as $columna => $campo) {
                $fila[$campo] = $celda[$columna] ?? null;
            }

            if ($this->vacia($fila)) {
                continue;
            }

            $fila['fecha_compra'] = $this->fecha($fila['fecha_compra']);
            $filas[] = $fila;
        }

        return $filas;
    }

    /** @param  list<list<mixed>>  $celdas */
    private function filaDeEncabezado(array $celdas): int
    {
        foreach (array_slice($celdas, 0, 5, true) as $indice => $fila) {
            $coincide = collect(self::ENCABEZADOS)->every(
                fn (string $esperado, int $columna) => Normaliza::texto($fila[$columna] ?? null) === $esperado
            );
            if ($coincide) {
                return $indice;
            }
        }

        throw new ReglaNegocioException(
            'La pestaña «Equipos» no tiene las columnas donde se esperan (B «PISO», E «ACTUAL», '
            . 'F «ASIGNADO», M «Serie», N «Fecha de Compra»). Si se movió alguna, la carga no puede leerla.'
        );
    }

    /** @param  array<string, mixed>  $fila */
    private function vacia(array $fila): bool
    {
        return Normaliza::celda($fila['actual']) === null
            && Normaliza::celda($fila['asignado']) === null
            && Normaliza::celda($fila['serie']) === null;
    }

    private function fecha(mixed $valor): ?string
    {
        if (is_numeric($valor)) {
            return Date::excelToDateTimeObject((float) $valor)->format('Y-m-d');
        }

        $texto = Normaliza::celda($valor);
        if ($texto && preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return null;
    }
}

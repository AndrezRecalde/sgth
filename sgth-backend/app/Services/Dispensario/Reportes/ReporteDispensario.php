<?php

namespace App\Services\Dispensario\Reportes;

/**
 * Un reporte del Dispensario.
 *
 * Cada uno declara quién lo puede pedir y qué filtros admite; el catálogo
 * aplica el alcance y la pantalla dibuja los filtros a partir de eso. Agregar
 * un reporte es escribir una clase y registrarla en `CatalogoReportesDispensario`.
 */
interface ReporteDispensario
{
    public function clave(): string;

    public function titulo(): string;

    public function descripcion(): string;

    /** Lleva nombres de pacientes con datos de salud: la autoridad no lo ve. */
    public function nominal(): bool;

    /**
     * Los perfiles que lo pueden pedir (constantes de `AlcanceReporte`). La
     * administración siempre; la autoridad, solo si no es nominal.
     *
     * @return list<string>
     */
    public function perfiles(): array;

    /**
     * Los filtros que la pantalla debe ofrecer, además del período:
     * `profesional`, `especialidad`, `tipo_paciente`, `unidad`, `agrupacion`.
     *
     * @return list<string>
     */
    public function filtros(): array;

    /** @return list<array{clave: string, titulo: string}> */
    public function columnas(FiltrosReporte $filtros): array;

    /** @return list<array<string, scalar|null>> */
    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array;
}

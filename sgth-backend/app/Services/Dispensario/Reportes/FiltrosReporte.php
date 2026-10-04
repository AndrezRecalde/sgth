<?php

namespace App\Services\Dispensario\Reportes;

use Carbon\CarbonImmutable;

/** Los filtros de un reporte, ya validados por `FiltrosReporteDispensarioRequest`. */
final class FiltrosReporte
{
    public function __construct(
        public readonly CarbonImmutable $desde,
        public readonly CarbonImmutable $hasta,
        public readonly ?int $profesionalId = null,
        /** `medicina_general` u `odontologia`. */
        public readonly ?string $especialidad = null,
        /** `servidor` o `familiar`. */
        public readonly ?string $tipoPaciente = null,
        public readonly ?int $unidadId = null,
        /** Una de las `agrupaciones()` del reporte; sin ella, la primera suya. */
        public readonly ?string $agrupacion = null,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desde(array $datos): self
    {
        return new self(
            desde:         CarbonImmutable::parse($datos['desde'])->startOfDay(),
            hasta:         CarbonImmutable::parse($datos['hasta'])->endOfDay(),
            profesionalId: isset($datos['profesional_id']) ? (int) $datos['profesional_id'] : null,
            especialidad:  $datos['especialidad'] ?? null,
            tipoPaciente:  $datos['tipo_paciente'] ?? null,
            unidadId:      isset($datos['unidad_administrativa_id']) ? (int) $datos['unidad_administrativa_id'] : null,
            agrupacion:    $datos['agrupacion'] ?? null,
        );
    }

    public function periodo(): string
    {
        return $this->desde->format('d/m/Y') . ' al ' . $this->hasta->format('d/m/Y');
    }
}

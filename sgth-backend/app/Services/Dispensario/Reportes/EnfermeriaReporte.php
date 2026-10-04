<?php

namespace App\Services\Dispensario\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * El trabajo de Enfermería en el período: los servicios por tipo, los
 * triajes por nivel de alerta y los signos vitales previos al FEMO.
 *
 * Agregado. Cada enfermera ve el suyo; la administración puede pedir el de
 * una. Lo anulado no cuenta.
 */
final class EnfermeriaReporte extends ReporteBase
{
    private const NIVELES = [
        'normal' => 'Normal', 'atencion' => 'Requiere atención',
        'critico' => 'Crítico', 'no_evaluado' => 'Sin valorar (menor de edad)',
    ];

    public function clave(): string { return 'enfermeria'; }

    public function titulo(): string { return 'Enfermería'; }

    public function descripcion(): string
    {
        return 'Servicios por tipo, triajes por nivel de alerta y signos vitales del FEMO.';
    }

    public function nominal(): bool { return false; }

    public function perfiles(): array
    {
        return [AlcanceReporte::ADMINISTRACION, AlcanceReporte::AUTORIDAD, AlcanceReporte::ENFERMERIA];
    }

    public function filtros(): array
    {
        return ['profesional'];
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        return self::columnasDe([['grupo', 'Grupo'], ['detalle', 'Detalle'], ['cantidad', 'Cantidad']]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $enfermera = $alcance->profesionalId ?? $filtros->profesionalId;
        $porEnfermera = fn ($q, string $columna) => $q->when($enfermera, fn ($q) => $q->where($columna, $enfermera));

        $servicios = $porEnfermera(DB::table('atenciones_enfermeria as a')
            ->join('catalogo_servicios_enfermeria as c', 'c.id', '=', 'a.catalogo_servicio_id')
            ->whereNull('a.deleted_at')->whereNull('a.anulado_en')
            ->whereBetween('a.atendido_en', [$filtros->desde, $filtros->hasta]), 'a.enfermera_id')
            ->groupBy('c.nombre')
            ->orderByRaw('COUNT(*) DESC')->orderBy('c.nombre')
            ->selectRaw('c.nombre as detalle, COUNT(*) as cantidad')
            ->get()
            ->map(fn ($f) => ['grupo' => 'Servicio de enfermería', 'detalle' => $f->detalle, 'cantidad' => (int) $f->cantidad]);

        $triajes = $porEnfermera(DB::table('triajes')
            ->whereBetween('registrado_en', [$filtros->desde, $filtros->hasta]), 'enfermera_id')
            ->groupBy('nivel_alerta')
            ->selectRaw('nivel_alerta, COUNT(*) as cantidad')
            ->pluck('cantidad', 'nivel_alerta');

        $signosSso = $porEnfermera(DB::table('solicitud_constantes_vitales')
            ->whereBetween('registrado_en', [$filtros->desde, $filtros->hasta]), 'enfermera_id')
            ->count();

        return $servicios
            ->concat(collect(self::NIVELES)
                ->filter(fn ($etiqueta, $nivel) => $triajes->has($nivel))
                ->map(fn ($etiqueta, $nivel) => ['grupo' => 'Triaje', 'detalle' => $etiqueta, 'cantidad' => (int) $triajes[$nivel]])
                ->values())
            ->when($signosSso > 0, fn ($c) => $c->push([
                'grupo' => 'Signos vitales del FEMO', 'detalle' => 'Tomas registradas', 'cantidad' => $signosSso,
            ]))
            ->values()
            ->all();
    }
}

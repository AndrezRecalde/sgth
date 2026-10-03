<?php

namespace App\Services\Dispensario\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * Cuánto falta la plantilla por enfermedad: los certificados médicos y sus
 * días de reposo, por unidad administrativa o por diagnóstico.
 *
 * Solo servidores: el ausentismo es del trabajo, y el certificado de un
 * familiar no le quita un día a nadie. Agregado: no lleva nombres, y por
 * unidad tampoco el diagnóstico de nadie. Lo anulado no cuenta.
 *
 * Los días son los del certificado entero aunque termine después del período:
 * es lo que se emitió en él.
 */
final class AusentismoReporte extends ReporteBase
{
    public function clave(): string { return 'ausentismo'; }

    public function titulo(): string { return 'Ausentismo por enfermedad'; }

    public function area(): string { return 'Atención clínica'; }

    public function descripcion(): string
    {
        return 'Certificados médicos y días de reposo de los servidores, por unidad o por diagnóstico.';
    }

    public function nominal(): bool { return false; }

    public function perfiles(): array
    {
        return [AlcanceReporte::ADMINISTRACION, AlcanceReporte::AUTORIDAD];
    }

    public function filtros(): array
    {
        return ['unidad'];
    }

    public function agrupaciones(): array
    {
        return ['unidad' => 'Unidad', 'diagnostico' => 'Diagnóstico'];
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        $primeras = $this->agrupacion($filtros) === 'diagnostico'
            ? [['cie10', 'CIE-10'], ['diagnostico', 'Diagnóstico']]
            : [['unidad', 'Unidad administrativa']];

        return self::columnasDe([
            ...$primeras,
            ['certificados', 'Certificados'],
            ['servidores', 'Servidores'],
            ['dias_reposo', 'Días de reposo'],
            ['promedio_dias', 'Promedio de días'],
        ]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $porDiagnostico = $this->agrupacion($filtros) === 'diagnostico';

        $query = DB::table('certificados_medicos as cert')
            ->join('consultas_medicas as cm', 'cm.id', '=', 'cert.consulta_medica_id')
            ->join('historias_clinicas as hc', 'hc.id', '=', 'cm.historia_clinica_id')
            ->join('servidores as s', 's.id', '=', 'hc.servidor_id')
            ->leftJoin('unidades_administrativas as ua', 'ua.id', '=', 's.unidad_administrativa_id')
            ->leftJoin('diagnosticos_cie10 as d', 'd.id', '=', 'cert.diagnostico_cie10_id')
            ->whereNull('cert.deleted_at')
            ->whereNull('cert.anulado_en')
            ->whereBetween('cert.fecha_inicio', [$filtros->desde->toDateString(), $filtros->hasta->toDateString()])
            ->when($filtros->unidadId, fn ($q) => $q->where('ua.id', $filtros->unidadId))
            ->selectRaw('COUNT(*) as certificados, COUNT(DISTINCT s.id) as servidores, COALESCE(SUM(cert.dias_reposo), 0) as dias_reposo');

        $query = $porDiagnostico
            ? $query->addSelect('d.codigo as cie10', 'd.descripcion as diagnostico')->groupBy('d.codigo', 'd.descripcion')
            : $query->addSelect('ua.nombre as unidad')->groupBy('ua.nombre');

        return $query->orderByDesc('dias_reposo')->orderByDesc('certificados')
            ->get()
            ->map(function ($f) use ($porDiagnostico) {
                $fila = $porDiagnostico
                    ? ['cie10' => $f->cie10 ?? '—', 'diagnostico' => $f->diagnostico ?? 'Sin diagnóstico registrado']
                    : ['unidad' => $f->unidad ?? 'Sin unidad asignada'];

                return [
                    ...$fila,
                    'certificados'  => (int) $f->certificados,
                    'servidores'    => (int) $f->servidores,
                    'dias_reposo'   => (int) $f->dias_reposo,
                    'promedio_dias' => $f->certificados ? round($f->dias_reposo / $f->certificados, 1) : 0,
                ];
            })
            ->all();
    }
}

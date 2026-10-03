<?php

namespace App\Services\Dispensario\Reportes;

use App\Enums\AptitudMedica;
use App\Enums\TipoFichaFemo;
use Illuminate\Support\Facades\DB;

/**
 * Las evaluaciones médicas ocupacionales (FEMO) cerradas en el período, por
 * tipo y con su aptitud, y cómo van las solicitudes de Talento Humano.
 *
 * Una ficha está cerrada cuando su solicitud está `completada`: mientras
 * siga `en_proceso` es un borrador del médico y no cuenta. Agregado.
 */
final class SaludOcupacionalReporte extends ReporteBase
{
    public function clave(): string { return 'salud_ocupacional'; }

    public function titulo(): string { return 'Salud ocupacional'; }

    public function descripcion(): string
    {
        return 'Evaluaciones FEMO cerradas por tipo y aptitud, y el estado de las solicitudes.';
    }

    public function area(): string { return 'Salud ocupacional'; }

    public function nominal(): bool { return false; }

    public function perfiles(): array
    {
        return [AlcanceReporte::ADMINISTRACION, AlcanceReporte::AUTORIDAD];
    }

    public function filtros(): array
    {
        return [];
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        return self::columnasDe([
            ['tipo', 'Tipo de evaluación'], ['evaluaciones', 'Evaluaciones'],
            ...array_map(fn (AptitudMedica $a) => [$a->value, $a->etiqueta()], AptitudMedica::cases()),
            ['solicitudes_pendientes', 'Solicitudes sin atender'],
            ['solicitudes_vencidas', 'De ellas, vencidas'],
        ]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $cerradas = DB::table('fichas_salud_ocupacional as f')
            ->join('solicitudes_certificacion_medica as s', 's.ficha_femo_id', '=', 'f.id')
            ->whereNull('f.deleted_at')
            ->where('s.estado', 'completada')
            ->whereBetween('f.fecha_evaluacion', [$filtros->desde->toDateString(), $filtros->hasta->toDateString()])
            ->groupBy('f.tipo_ficha', 'f.aptitud')
            ->selectRaw('f.tipo_ficha, f.aptitud, COUNT(*) as total')
            ->get()
            ->groupBy('tipo_ficha');

        // Las solicitudes abiertas se miran hoy, no en el período: lo que
        // importa es cuántas esperan y cuántas ya pasaron su plazo.
        $abiertas = DB::table('solicitudes_certificacion_medica')
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->groupBy('tipo_evento')
            ->selectRaw('tipo_evento, COUNT(*) as total, COUNT(*) FILTER (WHERE fecha_limite < CURRENT_DATE) as vencidas')
            ->get()
            ->keyBy('tipo_evento');

        return collect(TipoFichaFemo::cases())
            ->map(function (TipoFichaFemo $tipo) use ($cerradas, $abiertas) {
                $porAptitud = ($cerradas->get($tipo->value) ?? collect())->pluck('total', 'aptitud');
                $fila = ['tipo' => $tipo->etiqueta(), 'evaluaciones' => (int) $porAptitud->sum()];
                foreach (AptitudMedica::cases() as $aptitud) {
                    $fila[$aptitud->value] = (int) $porAptitud->get($aptitud->value, 0);
                }

                return [
                    ...$fila,
                    'solicitudes_pendientes' => (int) ($abiertas->get($tipo->value)->total ?? 0),
                    'solicitudes_vencidas'   => (int) ($abiertas->get($tipo->value)->vencidas ?? 0),
                ];
            })
            // Sin las filas en cero: un tipo sin nada que contar es ruido.
            ->filter(fn ($f) => $f['evaluaciones'] > 0 || $f['solicitudes_pendientes'] > 0)
            ->values()
            ->all();
    }
}

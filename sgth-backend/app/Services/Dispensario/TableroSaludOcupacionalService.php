<?php

namespace App\Services\Dispensario;

use App\Enums\AptitudMedica;
use App\Models\Dispensario\SolicitudCertificacionMedica;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Las cifras del tablero de salud ocupacional del Dispensario.
 *
 * Responde lo que la bandeja de solicitudes no podía: cuánto hay por atender y
 * cuánto lleva esperando, qué aptitudes se están emitiendo, qué diagnósticos
 * aparecen en las evaluaciones y a qué factores de riesgo está expuesta la
 * plantilla evaluada. La cobertura de evaluaciones periódicas la calcula
 * `CoberturaCertificacionService` y el tablero la pide aparte.
 *
 * Lo abierto (pendiente y en curso) es siempre «hoy»; lo emitido se mira por
 * año de la evaluación.
 */
final class TableroSaludOcupacionalService
{
    /** Tramos de antigüedad, en días desde que Talento Humano pidió la evaluación. */
    private const TRAMOS = [
        '0_3' => [0, 3],
        '4_7' => [4, 7],
        '8_15' => [8, 15],
        'mas_15' => [16, null],
    ];

    public function resumen(int $anio): array
    {
        return [
            'anio' => $anio,
            'bandeja' => $this->bandeja(),
            'antiguedad' => $this->antiguedad(),
            'abiertas_por_tipo' => $this->abiertasPorTipo(),
            'aptitud' => $this->aptitudDelAnio($anio),
            'diagnosticos' => $this->topDiagnosticos($anio),
            'factores_riesgo' => $this->topFactores($anio),
        ];
    }

    private function abiertas()
    {
        return SolicitudCertificacionMedica::query()->whereIn('estado', ['pendiente', 'en_proceso']);
    }

    public function bandeja(): array
    {
        $hoy = Carbon::today()->toDateString();

        return [
            'pendientes' => $this->abiertas()->where('estado', 'pendiente')->count(),
            'en_proceso' => $this->abiertas()->where('estado', 'en_proceso')->count(),
            'vencidas' => $this->abiertas()->whereDate('fecha_limite', '<', $hoy)->count(),
            // Esperan a Enfermería: el médico no puede iniciarlas todavía.
            'sin_triaje' => $this->abiertas()->where('estado', 'pendiente')
                ->whereDoesntHave('constantesVitales')->count(),
            // Llegan solas del cese: si se acumulan, alguien sale sin su
            // evaluación de retiro.
            'retiros' => $this->abiertas()->where('tipo_evento', 'retiro')->count(),
        ];
    }

    /** @return array<string, int> */
    private function antiguedad(): array
    {
        $hoy = Carbon::today();
        $conteo = array_fill_keys(array_keys(self::TRAMOS), 0);

        foreach ($this->abiertas()->pluck('created_at') as $creada) {
            $dias = (int) Carbon::parse($creada)->startOfDay()->diffInDays($hoy);
            foreach (self::TRAMOS as $clave => [$desde, $hasta]) {
                if ($dias >= $desde && ($hasta === null || $dias <= $hasta)) {
                    $conteo[$clave]++;
                    break;
                }
            }
        }

        return $conteo;
    }

    /** @return array<string, int> */
    private function abiertasPorTipo(): array
    {
        return $this->abiertas()
            ->select('tipo_evento', DB::raw('COUNT(*) as total'))
            ->groupBy('tipo_evento')
            ->pluck('total', 'tipo_evento')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Las aptitudes emitidas en el año: solo las de evaluaciones cerradas, que
     * son las que llegaron a Talento Humano. Un borrador no cuenta.
     *
     * @return array<string, int>
     */
    private function aptitudDelAnio(int $anio): array
    {
        $porAptitud = DB::table('solicitudes_certificacion_medica as s')
            ->join('fichas_salud_ocupacional as f', 'f.id', '=', 's.ficha_femo_id')
            ->where('s.estado', 'completada')
            ->whereNull('f.deleted_at')
            ->whereBetween('f.fecha_evaluacion', ["{$anio}-01-01", "{$anio}-12-31"])
            ->select('s.dictamen', DB::raw('COUNT(*) as total'))
            ->groupBy('s.dictamen')
            ->pluck('total', 'dictamen');

        $resultado = [];
        foreach (AptitudMedica::cases() as $aptitud) {
            $resultado[$aptitud->value] = (int) $porAptitud->get($aptitud->value, 0);
        }

        return $resultado;
    }

    /** Los diagnósticos CIE-10 más frecuentes en las fichas FEMO del año. */
    private function topDiagnosticos(int $anio, int $limite = 10): array
    {
        return DB::table('femo_diagnosticos as d')
            ->join('fichas_salud_ocupacional as f', 'f.id', '=', 'd.ficha_id')
            ->join('diagnosticos_cie10 as c', 'c.id', '=', 'd.diagnostico_cie10_id')
            ->whereNull('f.deleted_at')
            ->whereBetween('f.fecha_evaluacion', ["{$anio}-01-01", "{$anio}-12-31"])
            ->select('c.codigo', 'c.descripcion', DB::raw('COUNT(*) as total'))
            ->groupBy('c.id', 'c.codigo', 'c.descripcion')
            ->orderByDesc('total')
            ->orderBy('c.codigo')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => [
                'codigo' => $f->codigo,
                'descripcion' => $f->descripcion,
                'total' => (int) $f->total,
            ])
            ->all();
    }

    /**
     * Los factores de riesgo más marcados en las fichas del año, contados por
     * ficha (no por actividad): a cuántas personas evaluadas afecta. Orienta
     * qué exámenes pedir en la vigilancia de la salud.
     */
    private function topFactores(int $anio, int $limite = 10): array
    {
        return DB::table('femo_factores_riesgo as r')
            ->join('fichas_salud_ocupacional as f', 'f.id', '=', 'r.ficha_id')
            ->whereNull('f.deleted_at')
            ->where('r.presente', true)
            ->whereBetween('f.fecha_evaluacion', ["{$anio}-01-01", "{$anio}-12-31"])
            ->select('r.categoria', 'r.factor', DB::raw('COUNT(DISTINCT r.ficha_id) as fichas'))
            ->groupBy('r.categoria', 'r.factor')
            ->orderByDesc('fichas')
            ->orderBy('r.factor')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => [
                'categoria' => $f->categoria,
                'factor' => $f->factor,
                'fichas' => (int) $f->fichas,
            ])
            ->all();
    }
}

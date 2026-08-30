<?php

namespace App\Services\Estructura;

use App\Enums\TipoNombramiento;
use App\Models\Expediente\ContratoServidor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El estado de la plantilla: cuántas plazas hay, cuántas están ocupadas y por
 * qué modalidad está vinculada cada persona.
 *
 * No hay tabla nueva detrás. Todo sale de `puestos.plazas` y de los contratos
 * vigentes, con el mismo criterio de ocupación que usa la validación al
 * contratar (`ContratoServidor::scopeQueOcupanPlaza()`). Si el tablero contara
 * distinto que el validador, mostraría vacantes que no se pueden llenar.
 *
 * La ocupación se lee de los CONTRATOS y no de `servidores.puesto_id`: ese es
 * un campo derivado que puede quedar desincronizado, y de hecho ya ocurrió.
 */
class PlantillaService
{
    /**
     * @return array{
     *   plazas: array{total: int, ocupadas: int, vacantes: int, ocupacion: float},
     *   por_regimen: list<array<string, mixed>>,
     *   por_modalidad: list<array<string, mixed>>,
     *   por_unidad: list<array<string, mixed>>,
     *   sin_plaza: array<string, mixed>
     * }
     */
    public function resumen(): array
    {
        // Una sola consulta para las tres vistas de plazas. Agrupar en PHP a
        // partir de aquí evita repetir el criterio de ocupación tres veces en
        // SQL, que es justo como se desincronizó antes.
        $puestos = $this->puestosConOcupacion();

        $total    = (int) $puestos->sum('plazas');
        $ocupadas = (int) $puestos->sum('ocupadas');

        return [
            'plazas' => [
                'total'     => $total,
                'ocupadas'  => $ocupadas,
                'vacantes'  => max(0, $total - $ocupadas),
                'ocupacion' => $total > 0 ? round($ocupadas * 100 / $total, 1) : 0.0,
            ],
            'por_regimen'   => $this->agrupar($puestos, 'regimen_laboral', 'regimen'),
            'por_modalidad' => $this->personalPorModalidad(),
            'por_unidad'    => $this->agrupar($puestos, 'unidad', 'unidad'),
            'sin_plaza'     => $this->vinculosSinPlaza(),
        ];
    }

    /**
     * Un renglón por puesto activo, con sus plazas y cuántas están ocupadas.
     *
     * El `leftJoin` con las condiciones dentro del `on` —y no en un `where`—
     * es lo que hace que un puesto sin nadie contratado siga apareciendo con
     * cero ocupadas en vez de desaparecer de la lista.
     */
    private function puestosConOcupacion(): Collection
    {
        return DB::table('puestos as p')
            ->join('unidades_administrativas as u', 'u.id', '=', 'p.unidad_administrativa_id')
            ->leftJoin('contratos_servidor as c', function ($join) {
                $join->on('c.puesto_id', '=', 'p.id')
                    ->where('c.estado', 'vigente')
                    ->whereNull('c.deleted_at')
                    // El reemplazo no consume plaza: la sigue teniendo el
                    // titular en comisión, cuyo contrato continúa vigente.
                    ->whereNull('c.cubre_movimiento_id')
                    ->whereNotIn('c.tipo_nombramiento', TipoNombramiento::valoresSinPlaza());
            })
            ->where('p.activo', true)
            ->whereNull('p.deleted_at')
            ->groupBy('p.id', 'p.regimen_laboral', 'p.plazas', 'u.id', 'u.nombre')
            ->select([
                'p.id',
                'p.regimen_laboral',
                'p.plazas',
                'u.id as unidad_id',
                'u.nombre as unidad',
            ])
            ->selectRaw('count(c.id) as ocupadas')
            ->get();
    }

    /**
     * Suma plazas y ocupación por una columna, y ordena por vacantes.
     *
     * Es el orden que sirve para decidir: lo que más falta por llenar primero.
     *
     * @return list<array<string, mixed>>
     */
    private function agrupar(Collection $puestos, string $columna, string $clave): array
    {
        return $puestos
            ->groupBy($columna)
            ->map(function (Collection $grupo, string $valor) use ($clave) {
                $plazas   = (int) $grupo->sum('plazas');
                $ocupadas = (int) $grupo->sum('ocupadas');

                return [
                    $clave     => $valor,
                    'plazas'   => $plazas,
                    'ocupadas' => $ocupadas,
                    'vacantes' => max(0, $plazas - $ocupadas),
                ];
            })
            ->sortByDesc('vacantes')
            ->values()
            ->all();
    }

    /**
     * Cuánta gente hay por modalidad de vínculo, ocupe plaza o no.
     *
     * Se recorre el enum para que una modalidad sin nadie salga en cero en vez
     * de desaparecer del tablero: «ningún nombramiento provisional» es un dato,
     * y una fila ausente se lee como un olvido del informe.
     *
     * @return list<array<string, mixed>>
     */
    private function personalPorModalidad(): array
    {
        $conteos = ContratoServidor::query()
            ->where('estado', 'vigente')
            ->groupBy('tipo_nombramiento')
            ->selectRaw('tipo_nombramiento, count(*) as total')
            ->pluck('total', 'tipo_nombramiento');

        return collect(TipoNombramiento::cases())
            ->map(fn (TipoNombramiento $tipo) => [
                'tipo_nombramiento' => $tipo->value,
                'etiqueta'          => $tipo->etiqueta(),
                'total'             => (int) ($conteos[$tipo->value] ?? 0),
                'ocupa_plaza'       => $tipo->ocupaPlaza(),
            ])
            ->all();
    }

    /**
     * Los vínculos que no consumen plaza, con el peso de los ocasionales.
     *
     * El porcentaje se da sobre el total de contratos vigentes. No se compara
     * contra ningún tope aquí: el límite del art. 58 de la LOSEP tiene
     * excepciones y la base de cálculo la fija cada institución, así que el
     * dato se entrega y la lectura la hace Talento Humano.
     *
     * @return array<string, mixed>
     */
    private function vinculosSinPlaza(): array
    {
        $totalVigentes = ContratoServidor::where('estado', 'vigente')->count();

        $contar = fn (TipoNombramiento $tipo) => ContratoServidor::where('estado', 'vigente')
            ->where('tipo_nombramiento', $tipo->value)
            ->count();

        $ocasionales = $contar(TipoNombramiento::SERVICIOS_OCASIONALES);

        return [
            'servicios_ocasionales'   => $ocasionales,
            'servicios_profesionales' => $contar(TipoNombramiento::SERVICIOS_PROFESIONALES),
            'total_vigentes'          => $totalVigentes,
            'porcentaje_ocasionales'  => $totalVigentes > 0
                ? round($ocasionales * 100 / $totalVigentes, 1)
                : 0.0,
        ];
    }
}

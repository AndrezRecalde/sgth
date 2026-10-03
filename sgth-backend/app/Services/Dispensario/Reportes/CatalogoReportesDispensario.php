<?php

namespace App\Services\Dispensario\Reportes;

use App\Contracts\Dispensario\CatalogoReportesDispensarioInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Los reportes del Dispensario y quién puede pedir cada uno.
 *
 * Agregar un reporte es escribir su clase y ponerla en `REPORTES`: la pantalla
 * lo ofrece sola, con los filtros que declare.
 */
final class CatalogoReportesDispensario implements CatalogoReportesDispensarioInterface
{
    /** @var list<class-string<ReporteDispensario>> */
    private const REPORTES = [
        RegistroAtencionesReporte::class,
        MorbilidadReporte::class,
        ProduccionReporte::class,
    ];

    public function disponibles(AlcanceReporte $alcance): array
    {
        return collect(self::REPORTES)
            ->map(fn (string $clase) => app($clase))
            ->filter(fn (ReporteDispensario $r) => $this->puede($r, $alcance))
            ->map(fn (ReporteDispensario $r) => [
                'clave'       => $r->clave(),
                'titulo'      => $r->titulo(),
                'descripcion' => $r->descripcion(),
                'nominal'     => $r->nominal(),
                // Quien solo ve lo suyo no elige profesional: sería elegirse a sí mismo.
                'filtros'     => $alcance->soloLoPropio()
                    ? array_values(array_diff($r->filtros(), ['profesional']))
                    : $r->filtros(),
            ])
            ->values()
            ->all();
    }

    public function opciones(AlcanceReporte $alcance): array
    {
        if ($alcance->soloLoPropio()) {
            return ['profesionales' => [], 'unidades' => []];
        }

        // También los inactivos: un reporte de meses atrás puede ser de
        // alguien que ya no atiende.
        $profesionales = DB::table('users as u')
            ->leftJoin('servidores as s', 's.id', '=', 'u.servidor_id')
            ->whereExists(fn ($q) => $q->from('model_has_roles as mr')
                ->join('roles as r', 'r.id', '=', 'mr.role_id')
                ->whereColumn('mr.model_id', 'u.id')
                ->where('mr.model_type', User::class)
                ->whereIn('r.name', ['medico', 'odontologo', 'enfermera']))
            ->selectRaw("u.id, COALESCE(NULLIF(TRIM(COALESCE(s.nombre, '') || ' ' || COALESCE(s.apellido, '')), ''), u.usuario_ti) as nombre")
            ->orderBy('nombre')->orderBy('u.id')
            ->get();

        $unidades = DB::table('unidades_administrativas')
            ->whereNull('deleted_at')
            ->orderBy('nombre')->orderBy('id')
            ->get(['id', 'nombre']);

        return [
            'profesionales' => $profesionales->map(fn ($p) => ['id' => (int) $p->id, 'nombre' => $p->nombre])->all(),
            'unidades'      => $unidades->map(fn ($u) => ['id' => (int) $u->id, 'nombre' => $u->nombre])->all(),
        ];
    }

    public function reporte(string $clave, AlcanceReporte $alcance): ReporteDispensario
    {
        $reporte = collect(self::REPORTES)
            ->map(fn (string $clase) => app($clase))
            ->first(fn (ReporteDispensario $r) => $r->clave() === $clave);

        if (!$reporte) {
            throw new HttpException(404, 'Ese reporte no existe.');
        }

        if (!$this->puede($reporte, $alcance)) {
            throw new HttpException(403, $alcance->soloAgregados() && $reporte->nominal()
                ? 'Este reporte lleva nombres de pacientes con diagnósticos: solo lo ve el Dispensario.'
                : 'Este reporte no está disponible para su rol.');
        }

        return $reporte;
    }

    public function generar(string $clave, FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $reporte = $this->reporte($clave, $alcance);

        return [
            'columnas' => $reporte->columnas($filtros),
            'filas'    => $reporte->filas($filtros, $alcance),
        ];
    }

    /**
     * La administración lo pide todo. La autoridad, nada nominal. Cada
     * profesional, lo que su reporte declare para su perfil.
     */
    private function puede(ReporteDispensario $reporte, AlcanceReporte $alcance): bool
    {
        if ($alcance->perfil === AlcanceReporte::ADMINISTRACION) {
            return true;
        }

        if ($alcance->soloAgregados() && $reporte->nominal()) {
            return false;
        }

        return in_array($alcance->perfil, $reporte->perfiles(), true);
    }
}

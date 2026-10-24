<?php

namespace App\Services\Dispensario;

use App\Catalogos\FactoresRiesgoMsp;
use App\Models\Dispensario\CatalogoExamen;
use Illuminate\Support\Facades\DB;

/**
 * Lo que pasa cuando un examen pasa a ser sensible (D8, MDT-2024-198): no se
 * exige para obtener o conservar el empleo, así que sale de todo lo
 * ocupacional (decisión del Dispensario, 2026-10-24).
 *
 * Desde ese momento la orden de la ficha ya no lo ofrece y el servidor lo
 * rechaza, pero un perfil ocupacional o la lista por riesgo que ya lo tenían
 * lo seguían enseñando, y guardar ese perfil daba error hasta quitarlo a mano.
 */
final class ExamenSensibleService
{
    /**
     * Lo quita de los perfiles ocupacionales y de la lista por riesgo.
     *
     * @return array{perfiles: list<string>, riesgos: list<string>} de dónde salió
     */
    public function quitarDeLoOcupacional(CatalogoExamen $examen): array
    {
        $perfiles = DB::table('perfil_examen_items as i')
            ->join('perfiles_examen as p', 'p.id', '=', 'i.perfil_examen_id')
            ->where('p.ocupacional', true)
            ->where('i.catalogo_examen_id', $examen->id)
            ->orderBy('p.nombre')
            ->get(['i.id', 'p.nombre']);

        $riesgos = DB::table('riesgo_examenes')
            ->where('catalogo_examen_id', $examen->id)
            ->orderBy('categoria')->orderBy('factor')
            ->get(['id', 'categoria', 'factor']);

        DB::table('perfil_examen_items')->whereIn('id', $perfiles->pluck('id'))->delete();
        DB::table('riesgo_examenes')->whereIn('id', $riesgos->pluck('id'))->delete();

        $categorias = FactoresRiesgoMsp::catalogo();

        return [
            'perfiles' => $perfiles->pluck('nombre')->values()->all(),
            'riesgos'  => $riesgos
                ->map(fn ($r) => ($categorias[$r->categoria]['etiqueta'] ?? $r->categoria) . ' · ' . $r->factor)
                ->values()->all(),
        ];
    }

    /** «Se quitó de los perfiles ocupacionales «X» y de la lista por riesgo: Físico · Ruido.» */
    public static function mensaje(array $quitado): string
    {
        $partes = [];
        if ($quitado['perfiles']) {
            $partes[] = (count($quitado['perfiles']) === 1 ? 'del perfil ocupacional ' : 'de los perfiles ocupacionales ')
                . implode(', ', array_map(fn ($p) => "«{$p}»", $quitado['perfiles']));
        }
        if ($quitado['riesgos']) {
            $partes[] = 'de la lista por riesgo (' . implode(', ', $quitado['riesgos']) . ')';
        }

        return $partes ? 'Como ahora es sensible, se quitó ' . implode(' y ', $partes) . '.' : '';
    }
}

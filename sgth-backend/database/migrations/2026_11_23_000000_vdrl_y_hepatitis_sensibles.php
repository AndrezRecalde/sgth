<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * VDRL y hepatitis B y C pasan a ser exámenes sensibles (P3 de
 * docs/diseno-examenes-medicos.md, que los dejaba «a decidir»; decisión del
 * Dispensario del 2026-10-24). Desde ahora piden la constancia del
 * consentimiento firmado y no entran en lo ocupacional (D8).
 *
 * Como al marcarlos desde el catálogo, salen de los perfiles ocupacionales y
 * de la lista por riesgo que los tuvieran. La hepatitis A no cambia.
 */
return new class extends Migration
{
    /** Por área y nombre, como se identifica un examen del catálogo (#546). */
    private const EXAMENES = [
        ['serologia', 'VDRL'],
        ['infecciosas', 'HBsAg cualitativo'],
        ['infecciosas', 'HBsAg IgG'],
        ['infecciosas', 'HCV cualitativo'],
        ['infecciosas', 'HCV IgG'],
        ['infecciosas', 'HCV IgM'],
    ];

    public function up(): void
    {
        $ids = DB::table('catalogo_examenes')
            ->where(function ($q) {
                foreach (self::EXAMENES as [$area, $nombre]) {
                    $q->orWhere(fn ($w) => $w->where('area', $area)->where('nombre', $nombre));
                }
            })
            ->pluck('id');

        DB::table('catalogo_examenes')->whereIn('id', $ids)->update(['sensible' => true, 'updated_at' => now()]);

        DB::table('perfil_examen_items')
            ->whereIn('catalogo_examen_id', $ids)
            ->whereIn('perfil_examen_id', DB::table('perfiles_examen')->where('ocupacional', true)->select('id'))
            ->delete();
        DB::table('riesgo_examenes')->whereIn('catalogo_examen_id', $ids)->delete();
    }

    public function down(): void
    {
        // Lo que salió de los perfiles y de la lista no se repone: no queda
        // constancia de dónde estaba. Solo se devuelve la marca.
        foreach (self::EXAMENES as [$area, $nombre]) {
            DB::table('catalogo_examenes')->where('area', $area)->where('nombre', $nombre)
                ->update(['sensible' => false, 'updated_at' => now()]);
        }
    }
};

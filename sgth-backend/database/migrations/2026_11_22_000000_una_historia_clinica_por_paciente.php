<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una historia clínica viva por servidor y por familiar (hallazgo del
 * 2026-10-23). Había un índice único por cédula, pero no por dueño, y un
 * servidor con una historia sin cédula acabó con dos.
 *
 * Parciales: una historia fusionada se retira en blando y se queda con su
 * dueño, para la constancia. Antes hay que juntar las que hubiera con
 * `sgth:historias:fusionar`: si quedan, la migración no sigue y lo dice.
 */
return new class extends Migration
{
    public function up(): void
    {
        $repetidos = collect(['servidor_id', 'carga_familiar_id'])
            ->sum(fn (string $dueno) => DB::table('historias_clinicas')
                ->whereNull('deleted_at')
                ->whereNotNull($dueno)
                ->groupBy($dueno)
                ->havingRaw('count(*) > 1')
                ->select($dueno)
                ->get()
                ->count());

        if ($repetidos > 0) {
            throw new RuntimeException(
                "Hay {$repetidos} paciente(s) con más de una historia clínica. Júntelas antes: "
                . 'php artisan sgth:historias:fusionar --simular, y después con --responsable="…".'
            );
        }

        DB::statement('CREATE UNIQUE INDEX uq_hc_servidor_vivo ON historias_clinicas (servidor_id)
            WHERE servidor_id IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX uq_hc_carga_familiar_viva ON historias_clinicas (carga_familiar_id)
            WHERE carga_familiar_id IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_hc_servidor_vivo');
        DB::statement('DROP INDEX IF EXISTS uq_hc_carga_familiar_viva');
    }
};

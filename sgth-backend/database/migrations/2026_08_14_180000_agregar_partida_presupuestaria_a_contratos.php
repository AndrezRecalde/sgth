<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La partida que paga un vínculo depende de la modalidad, no del puesto.
 *
 * La Dirección Financiera confirmó en agosto de 2026 la correspondencia: un
 * empleado se imputa a 510105, un contrato ocasional a 510510, un obrero a
 * 710106, un contrato profesional a 530606 o 730606 según lo financie gasto
 * corriente o de inversión.
 *
 * Eso rompe el modelo anterior, que guardaba la partida en el puesto. Un mismo
 * puesto de carrera puede estar ocupado por nombramiento permanente (510105) o
 * por contrato ocasional (510510): con una sola partida en el puesto, cualquier
 * acción sobre el otro vínculo congelaba la equivocada en un documento firmado.
 *
 * `puestos.partida_presupuestaria_id` se conserva: sigue siendo la partida con
 * la que el orgánico presupuesta la plaza, y es de dónde se propone la del
 * vínculo cuando no hay nada mejor. Lo que cambia es cuál manda al pagar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->foreignId('partida_presupuestaria_id')
                ->nullable()
                ->after('remuneracion')
                ->constrained('partidas_presupuestarias')
                ->nullOnDelete();
        });

        // Los vínculos que ya existen heredan la del puesto. No es
        // necesariamente la correcta —por eso existe este cambio— pero es el
        // único dato disponible y deja el sistema coherente con lo que venía
        // mostrando hasta hoy. Talento Humano la corrige donde corresponda.
        DB::statement('
            UPDATE contratos_servidor c
               SET partida_presupuestaria_id = p.partida_presupuestaria_id
              FROM puestos p
             WHERE p.id = c.puesto_id
               AND p.partida_presupuestaria_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partida_presupuestaria_id');
        });
    }
};

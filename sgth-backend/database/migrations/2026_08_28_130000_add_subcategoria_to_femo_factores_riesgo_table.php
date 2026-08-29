<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La categoría «De seguridad» del formulario 028 se subdivide en locativos,
 * mecánicos, eléctricos y otros. El modelo era plano —`categoria` + `factor`—
 * y no podía expresar esa jerarquía, que el PDF sí tiene que reproducir.
 *
 * Queda nula en las otras cinco categorías, donde los factores cuelgan directo.
 * No la manda el cliente: la resuelve el servidor desde el catálogo a partir
 * de la categoría y el factor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('femo_factores_riesgo', function (Blueprint $table) {
            $table->string('subcategoria', 30)
                ->nullable()
                ->after('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('femo_factores_riesgo', function (Blueprint $table) {
            $table->dropColumn('subcategoria');
        });
    }
};

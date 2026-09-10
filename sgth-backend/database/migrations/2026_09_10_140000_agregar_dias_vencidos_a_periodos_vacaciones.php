<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días que se perdieron por pasar el tope de acumulación.
 *
 * No son días gozados, así que no pueden ir a `dias_utilizados`: el resumen
 * los contaría como vacaciones y una anulación podría devolverlos. Van aparte,
 * y el saldo pasa a ser generados − utilizados − vencidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodos_vacaciones', function (Blueprint $table) {
            $table->decimal('dias_vencidos', 8, 2)->default(0)->after('dias_utilizados');
        });
    }

    public function down(): void
    {
        Schema::table('periodos_vacaciones', function (Blueprint $table) {
            $table->dropColumn('dias_vencidos');
        });
    }
};

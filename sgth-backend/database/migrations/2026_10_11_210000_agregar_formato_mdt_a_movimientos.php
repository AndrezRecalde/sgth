<?php

use App\Enums\TipoActoDeOrigen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El documento de la acción de personal con el formato del Ministerio (diseño,
 * 6.5; fase 6.1).
 *
 * - `base_legal`: la que imprime la explicación. Sale de la clase y la causal,
 *   se corrige mientras es borrador y queda congelada al suscribir. Las
 *   anteriores, vacías, imprimen la de su clase.
 * - `acto_origen_tipo`, `acto_origen_numero`, `acto_origen_fecha`: el decreto,
 *   acuerdo o resolución que la origina, que el encabezado del formulario pide.
 *   `resolucion_numero` sigue siendo la referencia de la explicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->text('base_legal')->nullable()->after('descripcion');
            $table->string('acto_origen_tipo', 20)->nullable()->after('resolucion_numero');
            $table->string('acto_origen_numero', 100)->nullable()->after('acto_origen_tipo');
            $table->date('acto_origen_fecha')->nullable()->after('acto_origen_numero');
        });

        $tipos = implode(', ', array_map(fn (string $t) => "'{$t}'", TipoActoDeOrigen::valores()));

        DB::statement("ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_acto_origen_tipo_check
            CHECK (acto_origen_tipo IS NULL OR acto_origen_tipo IN ({$tipos}))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_acto_origen_tipo_check');

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn(['base_legal', 'acto_origen_tipo', 'acto_origen_numero', 'acto_origen_fecha']);
        });
    }
};

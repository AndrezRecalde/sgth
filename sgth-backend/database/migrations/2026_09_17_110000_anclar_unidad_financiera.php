<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La unidad de Gestión Financiera se ancla con una bandera, como ya se hacía
 * con Talento Humano y la máxima autoridad.
 *
 * Viáticos la tenía fija por número de registro —`UNIDAD_FINANCIERA_ID = 32`—,
 * y el comprobante contable la buscaba por nombre («%Financier%»). Lo mismo se
 * intentó en Acciones de Personal y no sirvió: en el orgánico hay unidades con
 * nombres parecidos, y el número de registro cambia entre instalaciones.
 *
 * Se marca sola cuando no hay duda: una única unidad activa cuyo nombre
 * contiene «financ». Si hay varias, o ninguna, queda por marcar en Estructura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades_administrativas', function (Blueprint $table) {
            $table->boolean('es_unidad_financiera')->default(false)->after('es_maxima_autoridad');
        });

        DB::statement('
            CREATE UNIQUE INDEX uq_unidad_financiera
            ON unidades_administrativas ((es_unidad_financiera))
            WHERE es_unidad_financiera AND deleted_at IS NULL
        ');

        $candidatas = DB::table('unidades_administrativas')
            ->whereNull('deleted_at')
            ->where('estado', true)
            ->where('nombre', 'ilike', '%financ%')
            ->pluck('id');

        if ($candidatas->count() === 1) {
            DB::table('unidades_administrativas')
                ->where('id', $candidatas->first())
                ->update(['es_unidad_financiera' => true]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_unidad_financiera');

        Schema::table('unidades_administrativas', function (Blueprint $table) {
            $table->dropColumn('es_unidad_financiera');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La campaña de preventivo del proveedor (fase 5.1b, P12): la hace la empresa
 * de la vigencia tecnológica y cada equipo hecho es un mantenimiento del
 * proveedor contado contra su contrato. No necesita técnico de TIC (puede
 * llevar uno que acompañe) ni un lugar: sin ubicación entran todos los
 * equipos del contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campanias_mantenimiento', function (Blueprint $table) {
            // tic, proveedor
            $table->string('realizado_por', 10)->default('tic');
            $table->foreignId('contrato_vigencia_id')->nullable()->constrained('contratos_vigencia');
        });
        DB::statement('ALTER TABLE campanias_mantenimiento ALTER COLUMN tecnico_id DROP NOT NULL');
        DB::statement('ALTER TABLE campanias_mantenimiento ALTER COLUMN ubicacion_id DROP NOT NULL');
        // La de TIC sigue pidiendo técnico y lugar, y no lleva contrato; la del proveedor, siempre su contrato.
        DB::statement(<<<'SQL'
            ALTER TABLE campanias_mantenimiento ADD CONSTRAINT campanias_mantenimiento_quien_check CHECK (
                (realizado_por = 'tic' AND tecnico_id IS NOT NULL AND ubicacion_id IS NOT NULL AND contrato_vigencia_id IS NULL)
                OR (realizado_por = 'proveedor' AND contrato_vigencia_id IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE campanias_mantenimiento DROP CONSTRAINT campanias_mantenimiento_quien_check');
        // Las del proveedor no caben en la tabla de antes; sus mantenimientos siguen en las fichas.
        DB::table('campanias_mantenimiento')->where('realizado_por', 'proveedor')->delete();
        DB::statement('ALTER TABLE campanias_mantenimiento ALTER COLUMN ubicacion_id SET NOT NULL');
        DB::statement('ALTER TABLE campanias_mantenimiento ALTER COLUMN tecnico_id SET NOT NULL');
        Schema::table('campanias_mantenimiento', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contrato_vigencia_id');
            $table->dropColumn('realizado_por');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ata cada subrogación a la Acción de Personal que la respalda.
 *
 * Sin este enlace las dos vivían en paralelo: la subrogación nacía 'activa' y
 * su acción quedaba en 'borrador', de modo que el subrogante adquiría la
 * facultad de firmar —FirmanteAccionPersonalService lo antepone al titular—
 * sin que nadie hubiera suscrito el acto ni verificado la disponibilidad
 * presupuestaria que exige el Art. 105 de la LOSEP.
 *
 * Con el enlace, la subrogación puede esperar a que su acción se registre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->foreignId('movimiento_personal_id')
                ->nullable()
                ->after('registrado_por')
                ->constrained('movimientos_personal')
                ->nullOnDelete();
        });

        // 'pendiente' es el estado en que nace ahora: registrada pero sin
        // efecto hasta que su acción se apruebe.
        DB::statement('ALTER TABLE subrogaciones DROP CONSTRAINT IF EXISTS subrogaciones_estado_check');
        DB::statement(
            "ALTER TABLE subrogaciones ADD CONSTRAINT subrogaciones_estado_check
             CHECK (estado IN ('pendiente', 'activa', 'finalizada', 'cancelada'))"
        );
    }

    public function down(): void
    {
        // Las pendientes se cancelan: en el esquema anterior ese estado no
        // existe y dejarlas rompería la restricción.
        DB::table('subrogaciones')->where('estado', 'pendiente')->update(['estado' => 'cancelada']);

        DB::statement('ALTER TABLE subrogaciones DROP CONSTRAINT IF EXISTS subrogaciones_estado_check');
        DB::statement(
            "ALTER TABLE subrogaciones ADD CONSTRAINT subrogaciones_estado_check
             CHECK (estado IN ('activa', 'finalizada', 'cancelada'))"
        );

        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('movimiento_personal_id');
        });
    }
};

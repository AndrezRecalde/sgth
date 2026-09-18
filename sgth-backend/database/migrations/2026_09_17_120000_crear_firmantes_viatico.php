<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quién firmó cada documento del viático, sellado al emitirlo.
 *
 * Los tres PDF resolvían las firmas en el momento de imprimir, así que un
 * informe reimpreso meses después salía con quien ocupara el cargo ese día.
 * Las autoridades rotan y se subrogan: el prefecto que autoriza la salida puede
 * estar de vacaciones cuando el servidor presenta el informe, y entonces firma
 * el subrogante. Decidido con Gestión Financiera el 2026-09-15.
 *
 * Una fila por documento y rol. Tres documentos por tres firmantes serían
 * treinta y seis columnas en `viaticos`; aquí son nueve filas como mucho.
 *
 * `aviso` guarda lo que no cuadraba al sellar —el titular con vacaciones
 * aprobadas y sin subrogación registrada—, para que quede por escrito junto a
 * la firma y no solo en la pantalla de quien la emitió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viatico_firmantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viatico_id')->constrained('viaticos')->cascadeOnDelete();
            $table->string('documento', 20);
            $table->string('rol', 30);

            // El servidor puede borrarse del sistema; el nombre impreso no.
            $table->foreignId('servidor_id')->nullable()->constrained('servidores')->nullOnDelete();
            $table->string('nombre')->nullable();
            $table->string('cedula', 20)->nullable();
            $table->string('cargo');
            $table->boolean('subrogado')->default(false);
            $table->string('aviso')->nullable();
            $table->timestamp('sellado_en');

            $table->unique(['viatico_id', 'documento', 'rol'], 'uq_firmante_viatico');
        });

        DB::statement("
            ALTER TABLE viatico_firmantes
            ADD CONSTRAINT viatico_firmante_documento_check
            CHECK (documento IN ('solicitud', 'informe', 'comprobante'))
        ");

        DB::statement("
            ALTER TABLE viatico_firmantes
            ADD CONSTRAINT viatico_firmante_rol_check
            CHECK (rol IN ('maxima_autoridad', 'jefe_unidad', 'director_financiero'))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('viatico_firmantes');
    }
};

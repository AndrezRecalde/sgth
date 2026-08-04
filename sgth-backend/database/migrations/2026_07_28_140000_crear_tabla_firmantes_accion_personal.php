<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Designación de quién firma las Acciones de Personal en cada rol, con
 * vigencia. Reemplaza la heurística anterior de AccionPersonalPdfService, que
 * buscaba al firmante con LIKE '%Prefect%' sobre el nombre del cargo: si había
 * dos coincidencias tomaba la primera, y si el cargo se renombraba dejaba de
 * encontrar a nadie.
 *
 * La vigencia importa porque las autoridades rotan: permite saber quién era el
 * firmante designado en la fecha en que se suscribió cada acción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmantes_accion_personal', function (Blueprint $table) {
            $table->id();

            $table->string('rol_firma', 40);

            $table->foreignId('servidor_id')
                  ->constrained('servidores')
                  ->cascadeOnDelete();

            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();

            $table->text('observacion')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['rol_firma', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmantes_accion_personal');
    }
};

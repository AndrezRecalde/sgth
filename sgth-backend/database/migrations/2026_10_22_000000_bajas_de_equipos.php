<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La baja de los equipos (docs/diseno-subsistema-tic.md, 5.3; fase 1.6).
 *
 * Hasta aquí «dar de baja» cambiaba el estado del equipo y respondía que el
 * acta quedaba pendiente: no había registro de la baja, ni motivo guardado, ni
 * informe. Ahora la baja es un trámite:
 *
 * - el técnico la propone con su informe técnico (diagnóstico y recomendación);
 * - el jefe de TIC la aprueba —con el memorando de Quipux (D4)— o la rechaza;
 * - después se anota cuándo se entregó el equipo a Activos Fijos y Bodega.
 *
 * Un equipo tiene como mucho una baja en curso o aprobada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bajas_bien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_informatico_id')->constrained('bienes_informaticos')->restrictOnDelete();
            $table->string('estado', 20)->default('propuesta');
            $table->string('motivo', 30);
            $table->text('diagnostico');
            $table->text('recomendacion')->nullable();
            $table->date('fecha_informe');
            $table->foreignId('informado_por')->nullable()->constrained('users');
            // La baja de un componente que salió junto con su equipo principal.
            $table->foreignId('baja_principal_id')->nullable()->constrained('bajas_bien')->nullOnDelete();

            $table->date('fecha_baja')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->timestamp('aprobado_en')->nullable();
            $table->string('memorando_numero', 100)->nullable();
            $table->string('memorando_archivo')->nullable();
            $table->string('memorando_nombre')->nullable();
            $table->string('soporte_anterior', 30)->nullable();
            $table->date('entregado_activos_en')->nullable();

            $table->foreignId('rechazado_por')->nullable()->constrained('users');
            $table->timestamp('rechazado_en')->nullable();
            $table->string('motivo_rechazo', 500)->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha_informe']);
        });

        DB::statement("CREATE UNIQUE INDEX bajas_bien_vigente_unique ON bajas_bien (bien_informatico_id) WHERE estado IN ('propuesta', 'aprobada')");
    }

    public function down(): void
    {
        Schema::dropIfExists('bajas_bien');
    }
};

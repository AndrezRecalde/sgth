<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La constatación física de los equipos (fase 5.2): TIC recorre una ubicación
 * —un piso, una sede—, escanea las etiquetas y deja constancia de lo que está
 * en su lugar, lo que estaba registrado en otra parte y lo que no apareció.
 * Reemplaza las rutas `auditoria/*`, que eran simuladas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constataciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            // El lugar que se recorre; la unidad, si se quiere, acota lo esperado.
            $table->foreignId('ubicacion_id')->constrained('ubicaciones');
            $table->foreignId('unidad_administrativa_id')->nullable()->constrained('unidades_administrativas');
            $table->string('estado', 15)->default('en_curso');
            $table->text('observaciones')->nullable();
            $table->foreignId('iniciada_por')->constrained('users');
            $table->foreignId('cerrada_por')->nullable()->constrained('users');
            $table->timestamp('cerrada_en')->nullable();
            $table->timestamps();
            $table->index(['estado', 'created_at']);
        });

        Schema::create('constatacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constatacion_id')->constrained('constataciones')->cascadeOnDelete();
            $table->foreignId('bien_informatico_id')->constrained('bienes_informaticos');
            // en_su_lugar, otra_ubicacion, nuevo, de_baja, no_encontrado
            $table->string('resultado', 20);
            // Dónde lo tenía registrado el sistema cuando se escaneó.
            $table->foreignId('ubicacion_registrada_id')->nullable()->constrained('ubicaciones');
            $table->boolean('movido')->default(false);
            $table->string('observacion', 300)->nullable();
            $table->foreignId('escaneado_por')->nullable()->constrained('users');
            $table->timestamp('escaneado_en')->nullable();
            $table->timestamps();
            $table->unique(['constatacion_id', 'bien_informatico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constatacion_items');
        Schema::dropIfExists('constataciones');
    }
};

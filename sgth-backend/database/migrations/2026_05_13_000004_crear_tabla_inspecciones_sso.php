<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspecciones_sso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_administrativa_id')->constrained('unidades_administrativas')->restrictOnDelete();
            $table->date('fecha_inspeccion');
            $table->string('tipo_inspeccion', 100);
            $table->text('hallazgos')->nullable();
            $table->text('recomendaciones')->nullable();
            
            $table->boolean('estado')->default(true);
            $table->foreignId('inspector_id')->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('unidad_administrativa_id');
            $table->index('fecha_inspeccion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspecciones_sso');
    }
};

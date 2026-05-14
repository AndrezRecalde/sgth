<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riesgos_laborales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puesto_id')->constrained('puestos')->restrictOnDelete();
            $table->string('tipo_riesgo', 100);
            $table->string('descripcion');
            $table->string('probabilidad', 50);
            $table->string('consecuencia', 50);
            $table->string('nivel_riesgo', 50);
            $table->text('medidas_preventivas')->nullable();
            
            $table->boolean('estado')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('puesto_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riesgos_laborales');
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_ocupacionales', function (Blueprint $table) {
            $table->id();
            $table->string('grado_codigo', 20)->unique();
            $table->unsignedTinyInteger('grado_numerico')->nullable();
            $table->string('grupo', 100);
            $table->string('denominacion_generica', 100)->nullable();
            $table->decimal('rmu', 10, 2);
            $table->enum('regimen', ['losep', 'codigo_trabajo'])->default('losep');
            $table->enum('nivel_complejidad', ['bajo', 'medio', 'alto'])->nullable();
            $table->enum('rol_puesto', [
                'dignatario',
                'ejecucion_coordinacion',
                'ejecucion_procesos',
                'ejecucion_procesos_apoyo',
                'administrativo',
                'codigo_trabajo',
            ])->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_ocupacionales');
    }
};

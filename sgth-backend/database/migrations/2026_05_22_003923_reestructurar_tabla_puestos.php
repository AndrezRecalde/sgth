<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            // 1. Agregar FK a grupos_ocupacionales (nullable para CT)
            $table->foreignId('grupo_ocupacional_id')
                  ->nullable()
                  ->after('unidad_administrativa_id')
                  ->constrained('grupos_ocupacionales')
                  ->nullOnDelete();

            // 2. Agregar campo plazas (cuántos servidores puede tener)
            $table->unsignedSmallInteger('plazas')
                  ->default(1)
                  ->after('grupo_ocupacional_id');

            // 3. Agregar rol_puesto como enum
            $table->enum('rol_puesto', [
                'dignatario',
                'ejecucion_coordinacion',
                'ejecucion_procesos',
                'ejecucion_procesos_apoyo',
                'administrativo',
                'codigo_trabajo',
            ])->nullable()->after('plazas');

            // 4. Agregar nivel_complejidad
            $table->enum('nivel_complejidad', ['bajo', 'medio', 'alto'])
                  ->nullable()
                  ->after('rol_puesto');

            // 5. Agregar regimen_laboral
            $table->enum('regimen_laboral', ['losep', 'codigo_trabajo'])
                  ->default('losep')
                  ->after('nivel_complejidad');

            // 6. Eliminar campos que ya no aplican
            // rmu y grupo_ocupacional (string) se reemplazan por la FK
            $table->dropColumn(['grupo_ocupacional', 'grado_rmu', 'rmu']);

            // 7. Renombrar nivel → nivel_jerarquico para más claridad
            $table->renameColumn('nivel', 'nivel_jerarquico');

            // 8. Renombrar estado → activo para consistencia
            $table->renameColumn('estado', 'activo');

            // 9. Agregar misión del puesto (opcional, del manual)
            $table->text('mision')->nullable()->after('denominacion');
        });
    }

    public function down(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            $table->dropForeign(['grupo_ocupacional_id']);
            $table->dropColumn([
                'grupo_ocupacional_id',
                'plazas',
                'rol_puesto',
                'nivel_complejidad',
                'regimen_laboral',
                'mision',
            ]);
            $table->string('grupo_ocupacional');
            $table->unsignedTinyInteger('grado_rmu');
            $table->decimal('rmu', 10, 2);
            $table->renameColumn('nivel_jerarquico', 'nivel');
            $table->renameColumn('activo', 'estado');
        });
    }
};

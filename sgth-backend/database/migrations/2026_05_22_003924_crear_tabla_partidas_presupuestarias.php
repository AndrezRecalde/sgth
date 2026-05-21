<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidas_presupuestarias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('descripcion', 200);
            $table->string('grupo_gasto', 100)->default('Gastos en Personal');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('unidad_partida_presupuestaria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_administrativa_id')
                  ->constrained('unidades_administrativas')
                  ->cascadeOnDelete();
            $table->foreignId('partida_presupuestaria_id')
                  ->constrained('partidas_presupuestarias')
                  ->cascadeOnDelete();
            $table->unsignedSmallInteger('anio_fiscal')
                  ->default(date('Y'));
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique([
                'unidad_administrativa_id',
                'partida_presupuestaria_id',
                'anio_fiscal',
            ], 'unidad_partida_anio_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_partida_presupuestaria');
        Schema::dropIfExists('partidas_presupuestarias');
    }
};

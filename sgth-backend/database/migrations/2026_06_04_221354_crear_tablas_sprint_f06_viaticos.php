<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Servidores acompañantes del viático
        Schema::create('viatico_servidores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viatico_id')
                  ->constrained('viaticos')
                  ->cascadeOnDelete();
            $table->foreignId('servidor_id')
                  ->constrained('servidores');
            $table->boolean('es_titular')->default(false);
            $table->timestamps();

            $table->unique(['viatico_id', 'servidor_id']);
        });

        // 2. Actividades del informe de liquidación
        Schema::create('actividades_liquidacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_viatico_id')
                  ->constrained('liquidaciones_viatico')
                  ->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->text('descripcion');
            $table->string('lugar', 200);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // 3. Agregar campos a viaticos
        Schema::table('viaticos', function (Blueprint $table) {
            // Modalidad de anticipo
            $table->enum('modalidad_anticipo', [
                'sin_anticipo',
                'total',
                'parcial',
            ])->default('total')->after('monto_anticipo');

            // Tipo de viaje (para exterior, según Art.9 Acuerdo 327)
            $table->string('tipo_viaje', 50)
                  ->nullable()
                  ->after('tipo');

            // País destino (para viajes al exterior)
            $table->string('pais_destino', 100)
                  ->nullable()
                  ->after('tipo_viaje');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades_liquidacion');
        Schema::dropIfExists('viatico_servidores');
        Schema::table('viaticos', function (Blueprint $table) {
            $table->dropColumn([
                'modalidad_anticipo',
                'tipo_viaje',
                'pais_destino',
            ]);
        });
    }
};

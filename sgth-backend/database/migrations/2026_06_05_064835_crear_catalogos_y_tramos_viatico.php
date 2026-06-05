<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Catálogo de tipos de transporte
        Schema::create('catalogo_transportes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('codigo', 50)->unique();
            $table->enum('tipo_vehiculo', [
                'terrestre', 'aereo', 'maritimo', 'otro'
            ])->default('terrestre');
            $table->boolean('requiere_autorizacion')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // 2. Empresas de transporte por tipo
        Schema::create('empresas_transporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_transporte_id')
                  ->constrained('catalogo_transportes')
                  ->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo', 50)->unique();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // 3. Tramos del itinerario del viático
        Schema::create('tramos_viatico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viatico_id')
                  ->constrained('viaticos')
                  ->cascadeOnDelete();

            // Origen
            $table->enum('origen_tipo', ['nacional', 'internacional'])
                  ->default('nacional');
            $table->foreignId('origen_provincia_id')
                  ->nullable()
                  ->constrained('provincias');
            $table->foreignId('origen_canton_id')
                  ->nullable()
                  ->constrained('cantones');
            $table->string('origen_pais', 100)->nullable();
            $table->string('origen_ciudad', 150);

            // Destino
            $table->enum('destino_tipo', ['nacional', 'internacional'])
                  ->default('nacional');
            $table->foreignId('destino_provincia_id')
                  ->nullable()
                  ->constrained('provincias');
            $table->foreignId('destino_canton_id')
                  ->nullable()
                  ->constrained('cantones');
            $table->string('destino_pais', 100)->nullable();
            $table->string('destino_ciudad', 150);

            // Transporte
            $table->foreignId('empresa_transporte_id')
                  ->constrained('empresas_transporte');

            // Fechas
            $table->dateTime('datetime_salida');
            $table->dateTime('datetime_llegada');

            // Orden del tramo en el itinerario
            $table->unsignedSmallInteger('orden')->default(1);

            $table->timestamps();
        });

        // 4. Autorizaciones de vuelo (regenerada desde tramos)
        Schema::create('autorizaciones_vuelo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramo_viatico_id')
                  ->constrained('tramos_viatico')
                  ->cascadeOnDelete();
            $table->foreignId('viatico_id')
                  ->constrained('viaticos')
                  ->cascadeOnDelete();
            $table->string('documento_invitacion_ruta')->nullable();
            $table->text('justificacion')->nullable();
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])
                  ->default('pendiente');
            $table->foreignId('aprobado_por')->nullable()
                  ->constrained('users');
            $table->text('observacion_aprobador')->nullable();
            $table->dateTime('aprobado_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autorizaciones_vuelo');
        Schema::dropIfExists('tramos_viatico');
        Schema::dropIfExists('empresas_transporte');
        Schema::dropIfExists('catalogo_transportes');
    }
};

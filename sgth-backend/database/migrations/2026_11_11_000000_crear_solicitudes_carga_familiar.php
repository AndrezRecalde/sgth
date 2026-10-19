<?php

use App\Enums\EstadoSolicitudCarga;
use App\Enums\TipoSolicitudCarga;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las solicitudes de cargas familiares que el servidor presenta desde «Mi
 * perfil» (docs/diseno-cargas-familiares-portal.md, decidido el 2026-10-19).
 *
 * Una tabla aparte y no un estado dentro de `cargas_familiares`, a diferencia
 * de los títulos: el Dispensario y la licencia por cuidado de hijos leen las
 * cargas activas, y una pendiente no debe llegarles; la cédula de una carga es
 * única aunque la fila esté borrada, y una solicitud mal escrita la ocuparía.
 * Al validarse, el cambio se aplica sobre `cargas_familiares` con el mismo
 * código que usa Talento Humano (CargaFamiliarService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_carga_familiar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->string('tipo', 15);
            // La que se corrige o se da de baja; en un registro, la que se
            // creó o se recuperó al validarlo.
            $table->foreignId('carga_familiar_id')->nullable()->constrained('cargas_familiares')->restrictOnDelete();
            // Los datos propuestos. La cédula solo en un registro: no se corrige.
            $table->string('cedula', 10)->nullable();
            $table->string('nombres', 100)->nullable();
            $table->string('apellidos', 100)->nullable();
            $table->string('parentesco', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('genero', 10)->nullable();
            $table->text('motivo')->nullable();
            // El respaldo, en el disco privado: obligatorio para los hijos y
            // opcional para el cónyuge o conviviente (D11).
            $table->string('documento_ruta')->nullable();
            $table->string('documento_nombre')->nullable();
            $table->string('estado', 15)->default(EstadoSolicitudCarga::PENDIENTE->value);
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelto_en')->nullable();
            $table->string('motivo_rechazo', 500)->nullable();
            $table->timestamp('retirada_en')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index('servidor_id');
        });

        $tipos = implode(', ', array_map(fn (string $t) => "'{$t}'", TipoSolicitudCarga::valores()));
        $estados = implode(', ', array_map(fn (string $e) => "'{$e}'", EstadoSolicitudCarga::valores()));

        DB::statement("ALTER TABLE solicitudes_carga_familiar ADD CONSTRAINT solicitudes_carga_tipo_check CHECK (tipo IN ({$tipos}))");
        DB::statement("ALTER TABLE solicitudes_carga_familiar ADD CONSTRAINT solicitudes_carga_estado_check CHECK (estado IN ({$estados}))");
        // El registro trae al familiar entero.
        DB::statement("ALTER TABLE solicitudes_carga_familiar ADD CONSTRAINT solicitudes_carga_alta_check
            CHECK (tipo <> 'alta' OR (cedula IS NOT NULL AND nombres IS NOT NULL AND apellidos IS NOT NULL
                AND parentesco IS NOT NULL AND fecha_nacimiento IS NOT NULL AND genero IS NOT NULL))");
        // La corrección, sobre una carga y con los datos nuevos; la baja, con su motivo.
        DB::statement("ALTER TABLE solicitudes_carga_familiar ADD CONSTRAINT solicitudes_carga_correccion_check
            CHECK (tipo <> 'correccion' OR (carga_familiar_id IS NOT NULL AND nombres IS NOT NULL AND apellidos IS NOT NULL
                AND parentesco IS NOT NULL AND fecha_nacimiento IS NOT NULL AND genero IS NOT NULL))");
        DB::statement("ALTER TABLE solicitudes_carga_familiar ADD CONSTRAINT solicitudes_carga_baja_check
            CHECK (tipo <> 'baja' OR (carga_familiar_id IS NOT NULL AND motivo IS NOT NULL))");
        // Validada, con la carga que quedó; rechazada, con su motivo.
        DB::statement("ALTER TABLE solicitudes_carga_familiar ADD CONSTRAINT solicitudes_carga_validada_check
            CHECK (estado <> 'validada' OR carga_familiar_id IS NOT NULL)");
        DB::statement("ALTER TABLE solicitudes_carga_familiar ADD CONSTRAINT solicitudes_carga_rechazada_check
            CHECK (estado <> 'rechazada' OR motivo_rechazo IS NOT NULL)");

        // Una pendiente por cédula y una por carga (regla 3), aunque lleguen
        // dos peticiones a la vez.
        DB::statement("CREATE UNIQUE INDEX solicitudes_carga_cedula_pendiente
            ON solicitudes_carga_familiar (cedula) WHERE estado = 'pendiente' AND tipo = 'alta'");
        DB::statement("CREATE UNIQUE INDEX solicitudes_carga_carga_pendiente
            ON solicitudes_carga_familiar (carga_familiar_id) WHERE estado = 'pendiente'");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_carga_familiar');
    }
};

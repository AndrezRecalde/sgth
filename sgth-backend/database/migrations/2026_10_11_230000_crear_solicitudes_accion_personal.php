<?php

use App\Enums\EstadoSolicitudAccion;
use App\Enums\TipoSolicitudAccion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las solicitudes del servidor desde el portal: renuncia, licencia sin
 * remuneración y jubilación (diseño, 9.13; fase 6.3b).
 *
 * Una tabla aparte y no un borrador directo: el titular no ve sus borradores
 * (diseño, 6.3), pero sí tiene que ver en qué quedó lo que pidió. Talento
 * Humano la atiende preparando la acción (`movimiento_personal_id`) o la
 * rechaza con el motivo; el servidor la retira mientras está pendiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_accion_personal', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->string('tipo', 30);
            // La causal de la licencia; la renuncia y la jubilación ya la traen.
            $table->string('causal', 40)->nullable();
            $table->date('fecha_presentacion');
            // Desde cuándo: el día en que deja el puesto, o el primero de la licencia.
            $table->date('fecha_desde');
            $table->date('fecha_hasta')->nullable();
            $table->text('motivo')->nullable();
            $table->string('documento_ruta');
            $table->string('documento_nombre');
            $table->string('estado', 15)->default(EstadoSolicitudAccion::PENDIENTE->value);
            $table->foreignId('movimiento_personal_id')->nullable()->constrained('movimientos_personal')->nullOnDelete();
            $table->foreignId('atendida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('atendida_en')->nullable();
            $table->string('motivo_rechazo', 500)->nullable();
            $table->timestamp('retirada_en')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'fecha_presentacion']);
            $table->index('servidor_id');
        });

        $tipos = implode(', ', array_map(fn (string $t) => "'{$t}'", TipoSolicitudAccion::valores()));
        $estados = implode(', ', array_map(fn (string $e) => "'{$e}'", EstadoSolicitudAccion::valores()));

        DB::statement("ALTER TABLE solicitudes_accion_personal ADD CONSTRAINT solicitudes_accion_tipo_check CHECK (tipo IN ({$tipos}))");
        DB::statement("ALTER TABLE solicitudes_accion_personal ADD CONSTRAINT solicitudes_accion_estado_check CHECK (estado IN ({$estados}))");
        // La licencia, con su causal y su fin.
        DB::statement("ALTER TABLE solicitudes_accion_personal ADD CONSTRAINT solicitudes_accion_licencia_check
            CHECK (tipo <> 'licencia_sin_remuneracion' OR (causal IS NOT NULL AND fecha_hasta IS NOT NULL AND fecha_hasta >= fecha_desde))");
        // Atendida, con su acción; rechazada, con su motivo.
        DB::statement("ALTER TABLE solicitudes_accion_personal ADD CONSTRAINT solicitudes_accion_atendida_check
            CHECK (estado <> 'atendida' OR movimiento_personal_id IS NOT NULL)");
        DB::statement("ALTER TABLE solicitudes_accion_personal ADD CONSTRAINT solicitudes_accion_rechazada_check
            CHECK (estado <> 'rechazada' OR motivo_rechazo IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_accion_personal');
    }
};

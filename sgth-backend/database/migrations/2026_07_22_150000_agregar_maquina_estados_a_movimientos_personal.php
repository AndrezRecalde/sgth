<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 1: solo agrega el esquema de la máquina de estados del evento
     * (EventoVinculo). Las guardas de transición, el bloqueo presupuestario
     * real y la notificación formal se implementan en fase 2.
     */
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->enum('categoria', [
                'accion_de_personal',
                'adenda_contractual',
                'movimiento_codigo_trabajo',
            ])->nullable()->after('tipo_movimiento');

            // Default 'registrada' preserva el comportamiento actual: todo
            // lo que ya se crea hoy sin pasar por un flujo de aprobación es,
            // de hecho, ya un registro histórico consumado.
            $table->enum('estado', [
                'borrador',
                'informe_uath',
                'dictamen_presupuestario',
                'suscrita',
                'registrada',
                'notificada',
                'anulada',
            ])->default('registrada')->after('categoria');

            $table->string('codigo_registro', 30)->nullable()->unique()->after('estado');
            $table->date('fecha_registro')->nullable()->after('codigo_registro');
            $table->string('dictamen_presupuestario_ref')->nullable()->after('fecha_registro');

            $table->foreignId('corrige_a_id')->nullable()
                ->after('dictamen_presupuestario_ref')
                ->constrained('movimientos_personal')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrige_a_id');
            $table->dropColumn([
                'categoria',
                'estado',
                'codigo_registro',
                'fecha_registro',
                'dictamen_presupuestario_ref',
            ]);
        });
    }
};

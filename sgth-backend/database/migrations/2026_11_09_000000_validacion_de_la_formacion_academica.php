<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los títulos y cursos que el servidor registra desde «Mi perfil» del portal
 * (decisión del 2026-10-17): llegan con su PDF y quedan por validar hasta que
 * Talento Humano los revisa.
 *
 * Lo que ya está en la tabla lo registró Talento Humano: entra validado, que
 * es el valor por defecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historial_academico_servidor', function (Blueprint $table) {
            $table->string('estado', 20)->default('validado')->after('codigo_senescyt');
            // El título, el certificado del curso o el registro de la SENESCYT.
            // Ruta en el disco privado: no viaja al cliente.
            $table->string('documento_ruta')->nullable()->after('estado');
            $table->string('documento_nombre')->nullable()->after('documento_ruta');
            $table->foreignId('registrado_por')->nullable()->after('documento_nombre')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('validado_por')->nullable()->after('registrado_por')
                ->constrained('users')->nullOnDelete();
            // Cuándo se validó o se rechazó: lo decide quien está en `validado_por`.
            $table->timestamp('validado_en')->nullable()->after('validado_por');
            $table->string('motivo_rechazo', 500)->nullable()->after('validado_en');

            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('historial_academico_servidor', function (Blueprint $table) {
            $table->dropIndex(['estado', 'created_at']);
            $table->dropConstrainedForeignId('registrado_por');
            $table->dropConstrainedForeignId('validado_por');
            $table->dropColumn(['estado', 'documento_ruta', 'documento_nombre', 'validado_en', 'motivo_rechazo']);
        });
    }
};

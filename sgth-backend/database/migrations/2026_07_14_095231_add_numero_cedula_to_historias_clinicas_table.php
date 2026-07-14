<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historias_clinicas', function (Blueprint $table) {
            $table->string('numero_historia', 20)
                  ->nullable()
                  ->after('id')
                  ->comment('Número de HCE = cédula del paciente');
            $table->string('cedula_paciente', 20)
                  ->nullable()
                  ->after('numero_historia');
            $table->enum('tipo_paciente', [
                'servidor', 'familiar', 'candidato',
            ])->default('servidor')->after('cedula_paciente');
        });

        // Migrar datos existentes:
        // Para servidores → cedula del servidor
        DB::statement("
            UPDATE historias_clinicas hc
            SET cedula_paciente = s.cedula,
                numero_historia = s.cedula,
                tipo_paciente   = 'servidor'
            FROM servidores s
            WHERE hc.servidor_id = s.id
            AND hc.cedula_paciente IS NULL
        ");

        // Para cargas familiares → cedula de la carga
        DB::statement("
            UPDATE historias_clinicas hc
            SET cedula_paciente = cf.cedula,
                numero_historia = cf.cedula,
                tipo_paciente   = 'familiar'
            FROM cargas_familiares cf
            WHERE hc.carga_familiar_id = cf.id
            AND hc.cedula_paciente IS NULL
        ");

        // Agregar unique constraint
        Schema::table('historias_clinicas', function (Blueprint $table) {
            $table->unique('cedula_paciente', 'uq_hc_cedula_paciente');
        });
    }

    public function down(): void
    {
        Schema::table('historias_clinicas', function (Blueprint $table) {
            $table->dropUnique('uq_hc_cedula_paciente');
            $table->dropColumn([
                'numero_historia',
                'cedula_paciente',
                'tipo_paciente',
            ]);
        });
    }
};

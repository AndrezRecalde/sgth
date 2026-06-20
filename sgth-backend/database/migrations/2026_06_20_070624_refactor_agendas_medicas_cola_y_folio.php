<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agendas_medicas', function (Blueprint $table) {
            $table->string('folio', 30)->unique()->nullable()
                  ->after('id');
            $table->string('tipo_atencion', 30)
                  ->default('medicina_general')
                  ->after('beneficiario_id');
            // 'medicina_general' | 'odontologia'

            $table->timestamp('registrado_en')->nullable()
                  ->after('motivo_solicitud');

            $table->string('hora_inicio', 10)->nullable()->change();
            $table->string('hora_fin', 10)->nullable()->change();
            $table->string('motivo_solicitud', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

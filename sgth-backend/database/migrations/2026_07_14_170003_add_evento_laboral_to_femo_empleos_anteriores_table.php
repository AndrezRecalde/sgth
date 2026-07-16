<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('femo_empleos_anteriores', function (Blueprint $table) {
            $table->enum('tipo_evento_laboral', [
                'ninguno', 'incidente', 'accidente', 'enfermedad_profesional',
            ])->default('ninguno')->after('observaciones');
            $table->boolean('calificado_iess')->nullable()->after('tipo_evento_laboral');
            $table->date('fecha_evento')->nullable()->after('calificado_iess');
            $table->text('especificar')->nullable()->after('fecha_evento');
        });
    }

    public function down(): void
    {
        Schema::table('femo_empleos_anteriores', function (Blueprint $table) {
            $table->dropColumn(['tipo_evento_laboral', 'calificado_iess', 'fecha_evento', 'especificar']);
        });
    }
};

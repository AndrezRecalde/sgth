<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->string('segundo_nombre', 150)->nullable()->after('nombres');
            $table->string('segundo_apellido', 150)->nullable()->after('apellidos');
            $table->string('genero', 20)->nullable()->after('correo');
            $table->string('estado_civil', 20)->nullable()->after('genero');
            $table->date('fecha_nacimiento')->nullable()->after('estado_civil');
            $table->string('tipo_sangre', 5)->nullable()->after('fecha_nacimiento');
            $table->boolean('tiene_discapacidad')->default(false)->after('tipo_sangre');
            $table->string('porcentaje_discapacidad', 10)->nullable()->after('tiene_discapacidad');
            $table->unsignedBigInteger('provincia_nacimiento_id')->nullable()->after('porcentaje_discapacidad');
            $table->unsignedBigInteger('canton_nacimiento_id')->nullable()->after('provincia_nacimiento_id');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn([
                'segundo_nombre', 'segundo_apellido',
                'genero', 'estado_civil', 'fecha_nacimiento',
                'tipo_sangre', 'tiene_discapacidad',
                'porcentaje_discapacidad',
                'provincia_nacimiento_id', 'canton_nacimiento_id',
            ]);
        });
    }
};

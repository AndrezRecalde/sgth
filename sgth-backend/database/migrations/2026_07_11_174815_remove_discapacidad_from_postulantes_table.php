<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn([
                'tiene_discapacidad',
                'porcentaje_discapacidad',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->boolean('tiene_discapacidad')
                  ->default(false)
                  ->after('tipo_sangre');
            $table->string('porcentaje_discapacidad', 10)
                  ->nullable()
                  ->after('tiene_discapacidad');
        });
    }
};

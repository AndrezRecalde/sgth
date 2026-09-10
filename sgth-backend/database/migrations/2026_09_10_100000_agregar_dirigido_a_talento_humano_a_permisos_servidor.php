<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Si el permiso se dirige al jefe de Talento Humano en vez del inmediato.
     *
     * `jefe_id` por sí solo no basta para saberlo: el jefe de Talento Humano es
     * también el jefe inmediato de quienes trabajan en esa unidad, y el PDF
     * tiene que imprimir el rótulo correcto sobre la línea de firma. Se guarda
     * la decisión en vez de deducirla comparando ids.
     */
    public function up(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->boolean('dirigido_a_talento_humano')
                  ->default(false)
                  ->after('jefe_id');
        });
    }

    public function down(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->dropColumn('dirigido_a_talento_humano');
        });
    }
};

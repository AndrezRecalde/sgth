<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activos Fijos y Bodega en la constatación física (fase 5.2b, P13): cuando
 * esa unidad participa en el recorrido, el acta lleva una tercera firma, la de
 * su responsable. Se marca al abrir la constatación y se puede cambiar
 * mientras sigue en curso; quién firma sale del organigrama y queda sellado al
 * cerrarla, para que el acta se reimprima igual aunque después cambie el jefe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('constataciones', function (Blueprint $table) {
            $table->boolean('participa_activos_fijos')->default(false);
            // {nombre, cargo}: vacío hasta el cierre; un puesto vacante sale con el cargo y sin nombre.
            $table->json('firmante_activos_fijos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('constataciones', function (Blueprint $table) {
            $table->dropColumn(['participa_activos_fijos', 'firmante_activos_fijos']);
        });
    }
};

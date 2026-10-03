<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El perímetro abdominal se toma en el triaje.
 *
 * La sección E del FEMO lo pide y la ficha tiene la columna, pero las
 * constantes vitales las toma Enfermería antes de que el médico abra la ficha,
 * y su formulario no lo pedía: no había forma de registrarlo (por eso el
 * ejemplo del Dispensario dice «NO DATO»). Ahora el FEMO copia del triaje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud_constantes_vitales', function (Blueprint $table) {
            $table->decimal('perimetro_abdominal_cm', 5, 2)->nullable()->after('talla_cm');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_constantes_vitales', function (Blueprint $table) {
            $table->dropColumn('perimetro_abdominal_cm');
        });
    }
};

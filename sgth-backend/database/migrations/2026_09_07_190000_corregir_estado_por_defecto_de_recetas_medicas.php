<?php

use App\Enums\EstadoReceta;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `recetas_medicas.estado` nacía con el valor por defecto `emitida`, un estado
 * que no escribe ni entiende nadie: RecetaService solo produce `pendiente`,
 * `despachada_parcial`, `despachada_completa` y `anulada`. Cualquier inserción
 * que omitiera la columna dejaba la receta en un estado que el listado no sabe
 * contar y que ningún filtro alcanza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recetas_medicas', function (Blueprint $table) {
            $table->string('estado', 50)
                  ->default(EstadoReceta::PENDIENTE->value)
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('recetas_medicas', function (Blueprint $table) {
            $table->string('estado', 50)->default('emitida')->change();
        });
    }
};

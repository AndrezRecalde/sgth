<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deja recetar lo que la farmacia no maneja.
 *
 * Hasta aquí un ítem de receta era, por fuerza, una fila del catálogo:
 * `inventario_medicina_id` era una FK obligatoria y no había ningún sitio
 * donde escribir un nombre. Eso convertía el inventario del dispensario en el
 * límite de lo que un médico podía prescribir, que es al revés de como se
 * receta: primero se decide el tratamiento y después se ve qué hay.
 *
 * La alternativa —dar de alta esos fármacos en el catálogo con stock cero— se
 * descartó porque `scopeBajoMinimo` compara contra `stock_minimo`, así que
 * todos habrían quedado para siempre en la alerta de reposición y en
 * Adquisiciones, pidiendo comprar algo que el dispensario no maneja.
 *
 * Un ítem es ahora una cosa o la otra, nunca las dos ni ninguna: lo garantiza
 * el CHECK, no solo la validación del formulario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items_receta', function (Blueprint $table) {
            $table->foreignId('inventario_medicina_id')->nullable()->change();

            $table->string('medicamento_externo')
                  ->nullable()
                  ->after('inventario_medicina_id');
        });

        DB::statement("
            ALTER TABLE items_receta
            ADD CONSTRAINT check_item_receta_medicamento
            CHECK (num_nonnulls(inventario_medicina_id, medicamento_externo) = 1)
        ");

        // Un ítem externo no se despacha nunca: no hay existencias que mover ni
        // kardex que escribir. Sin un estado propio se quedaría «pendiente»
        // para siempre y la receta no llegaría a cerrarse jamás.
        DB::statement('ALTER TABLE items_receta DROP CONSTRAINT items_receta_estado_check');

        DB::statement("
            ALTER TABLE items_receta
            ADD CONSTRAINT items_receta_estado_check
            CHECK (estado IN (
                'pendiente', 'despachado_parcial', 'despachado_completo',
                'no_disponible'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE items_receta DROP CONSTRAINT items_receta_estado_check');

        DB::statement("
            ALTER TABLE items_receta
            ADD CONSTRAINT items_receta_estado_check
            CHECK (estado IN (
                'pendiente', 'despachado_parcial', 'despachado_completo'
            ))
        ");

        DB::statement('ALTER TABLE items_receta DROP CONSTRAINT check_item_receta_medicamento');

        Schema::table('items_receta', function (Blueprint $table) {
            $table->dropColumn('medicamento_externo');
            $table->foreignId('inventario_medicina_id')->nullable(false)->change();
        });
    }
};

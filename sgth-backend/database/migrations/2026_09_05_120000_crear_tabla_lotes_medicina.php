<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El stock deja de ser un número en la ficha del medicamento y pasa a ser la
 * suma de sus lotes.
 *
 * Hasta ahora `inventario_medicinas` guardaba un solo `lote` y una sola
 * `fecha_caducidad`, y cada adquisición los sobrescribía: cien unidades que
 * caducan en marzo más cincuenta que caducan en diciembre eran una fila que
 * decía «ciento cincuenta, caduca en diciembre». De ahí salían los dos daños,
 * simétricos: despachar vencido cuando el último lote caduca más tarde, y
 * bloquear existencias buenas cuando caduca antes.
 *
 * `inventario_medicinas` se queda como catálogo —el producto— y estos lotes
 * llevan las existencias. `stock_actual` de la ficha sigue existiendo como
 * caché de la suma; el invariante que lo sostiene se comprueba en los tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes_medicina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_medicina_id')
                  ->constrained('inventario_medicinas')
                  ->restrictOnDelete();

            // De qué entrada vino. Null en los lotes que abrió la migración
            // inicial, que no proceden de ninguna adquisición registrada.
            $table->foreignId('item_adquisicion_id')
                  ->nullable()
                  ->constrained('items_adquisicion')
                  ->nullOnDelete();

            // Null es «sin identificar»: existencias reales cuyo lote nadie
            // anotó. Se despachan como cualquier otro lote, pero se pueden
            // reconocer para pedir un recuento físico.
            $table->string('codigo_lote')->nullable();
            $table->date('fecha_caducidad')->nullable();

            $table->integer('cantidad_ingresada');
            $table->integer('stock_actual');

            $table->timestamps();

            // El índice que usará FEFO: los lotes de una medicina, ordenados
            // por caducidad.
            $table->index(
                ['inventario_medicina_id', 'fecha_caducidad'],
                'idx_lotes_medicina_fefo'
            );
        });

        DB::statement('
            ALTER TABLE lotes_medicina
            ADD CONSTRAINT chk_lote_stock_no_negativo
            CHECK (stock_actual >= 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes_medicina');
    }
};

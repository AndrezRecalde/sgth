<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los dos índices que `sumarios` no tenía y `vistos_buenos` —la otra tabla del
 * mismo módulo— sí declara explícitamente.
 *
 * En PostgreSQL una clave foránea NO crea índice, al contrario que en MySQL, y
 * `servidor_id` se consulta en cada apertura de sumario (para rechazar un
 * segundo sumario en curso) y en el filtro por servidor del listado.
 *
 * El compuesto `estado` + `fecha_apertura` sirve al listado, que filtra por
 * estado y ordena por fecha de apertura descendente. El índice suelto de
 * `estado` que ya existía se mantiene: lo usan el control de plazos y las
 * consultas que no ordenan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sumarios', function (Blueprint $table) {
            $table->index('servidor_id');
            $table->index(['estado', 'fecha_apertura']);
        });
    }

    public function down(): void
    {
        Schema::table('sumarios', function (Blueprint $table) {
            $table->dropIndex(['servidor_id']);
            $table->dropIndex(['estado', 'fecha_apertura']);
        });
    }
};

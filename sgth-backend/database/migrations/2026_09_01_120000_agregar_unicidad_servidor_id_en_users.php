<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Un servidor tiene como máximo un usuario" era una regla que solo vivía en
 * PHP, con un patrón comprobar-luego-insertar sin bloqueo: dos altas simultáneas
 * sobre el mismo servidor podían pasar ambas la comprobación. Además la columna
 * no tenía ningún índice —una FK no lo crea sola en Postgres—, así que
 * whereDoesntHave('servidor') y la búsqueda de servidor ocupado hacían seq scan.
 *
 * El índice único resuelve las dos cosas a la vez. Postgres considera los NULL
 * distintos entre sí en un índice único, así que varios usuarios sin ficha de
 * servidor —un estado legítimo— siguen conviviendo sin choque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('servidor_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['servidor_id']);
        });
    }
};

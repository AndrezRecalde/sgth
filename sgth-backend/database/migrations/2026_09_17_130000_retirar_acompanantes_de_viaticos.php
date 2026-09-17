<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un viático, un servidor.
 *
 * Cuando viajaban varios, el módulo los guardaba como acompañantes de un mismo
 * viático: un solo monto, un solo anticipo y una sola liquidación para todos.
 * Gestión Financiera confirmó el 2026-09-15 que no es así —cada servidor tiene
 * su propio viático, su anticipo y su liquidación—, de modo que la figura del
 * acompañante desaparece.
 *
 * `viatico_servidores` guardaba a los acompañantes y, por duplicado, al
 * titular, que ya vive en `viaticos.servidor_id`. Se retira entera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('viatico_servidores');
    }

    public function down(): void
    {
        Schema::create('viatico_servidores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viatico_id')->constrained('viaticos')->cascadeOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->boolean('es_titular')->default(false);
            $table->timestamps();

            $table->unique(['viatico_id', 'servidor_id']);
        });

        // Al volver atrás, cada viático recupera al menos a su titular.
        DB::statement('
            INSERT INTO viatico_servidores (viatico_id, servidor_id, es_titular, created_at, updated_at)
            SELECT id, servidor_id, true, now(), now() FROM viaticos WHERE deleted_at IS NULL
        ');
    }
};

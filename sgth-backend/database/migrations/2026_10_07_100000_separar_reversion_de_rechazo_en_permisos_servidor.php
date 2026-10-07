<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quién revirtió la confirmación de un permiso, cuándo y por qué.
 *
 * Revertir guardaba su motivo en `motivo_rechazo`, así que un permiso que
 * volvía a PENDIENTE llevaba un «motivo de rechazo» sin haber sido rechazado,
 * y de quién lo revirtió no quedaba nada. Mientras ninguna pantalla enseñaba
 * esos motivos no se notaba; al mostrarlos, un pendiente diría «rechazado
 * porque…».
 *
 * Los que ya existen se trasladan: un `motivo_rechazo` en un permiso que NO
 * está rechazado solo pudo escribirlo una reversión, porque el rechazo es un
 * estado final. Quién y cuándo de esas reversiones antiguas no se sabe y queda
 * vacío.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->foreignId('revertido_por')
                ->nullable()
                ->after('motivo_anulacion')
                ->constrained('users');
            $table->timestamp('revertido_en')->nullable()->after('revertido_por');
            $table->text('motivo_reversion')->nullable()->after('revertido_en');
        });

        DB::table('permisos_servidor')
            ->where('estado', '<>', 'rechazado')
            ->whereNotNull('motivo_rechazo')
            ->update([
                'motivo_reversion' => DB::raw('motivo_rechazo'),
                'motivo_rechazo'   => null,
            ]);
    }

    public function down(): void
    {
        DB::table('permisos_servidor')
            ->where('estado', '<>', 'rechazado')
            ->whereNotNull('motivo_reversion')
            ->update([
                'motivo_rechazo' => DB::raw('motivo_reversion'),
            ]);

        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->dropForeign(['revertido_por']);
            $table->dropColumn(['revertido_por', 'revertido_en', 'motivo_reversion']);
        });
    }
};

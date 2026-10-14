<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qué características pide cada tipo de equipo (fase 5.3): un computador, su
 * procesador y su disco; una impresora, su conexión y su consumible; un
 * monitor, sus pulgadas; un escáner, cómo alimenta las hojas. Se dice en el
 * tipo, no en su nombre, porque TIC puede renombrarlo. Las características se
 * siguen guardando en `caracteristicas_tecnicas`.
 */
return new class extends Migration
{
    /** Ficha => nombres de tipo (en minúsculas) que la usan de entrada. */
    private const DE_ENTRADA = [
        'computador' => ['computador', 'portátil', 'portatil', 'laptop'],
        'impresora'  => ['impresora'],
        'monitor'    => ['monitor'],
        'escaner'    => ['escáner', 'escaner', 'scanner'],
    ];

    public function up(): void
    {
        Schema::table('tipos_bien', function (Blueprint $table) {
            $table->string('ficha', 20)->default('general')->after('anios_vida_util');
        });

        foreach (self::DE_ENTRADA as $ficha => $nombres) {
            DB::table('tipos_bien')->whereIn(DB::raw('lower(nombre)'), $nombres)->update(['ficha' => $ficha]);
        }
    }

    public function down(): void
    {
        Schema::table('tipos_bien', function (Blueprint $table) {
            $table->dropColumn('ficha');
        });
    }
};

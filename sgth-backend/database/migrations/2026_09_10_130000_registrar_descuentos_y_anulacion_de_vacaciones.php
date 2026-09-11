<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De qué período salió cada día de vacaciones, y la anulación.
 *
 * El descuento tocaba solo el período del año de la vacación: lo que no
 * alcanzaba ahí se perdía, aunque hubiera saldo en años anteriores. Ahora
 * reparte entre los períodos abiertos, del más antiguo al más nuevo, y
 * `vacacion_descuentos` anota cuánto tomó de cada uno. Sin esa anotación una
 * anulación no sabría a qué períodos devolver los días.
 *
 * `devuelto_en` no borra la fila: el descuento ocurrió, y la devolución
 * también. El historial queda entero.
 *
 * El estado `anulada` se agrega al CHECK que Laravel creó para el enum de
 * `vacaciones.estado`, con el mismo nombre que le puso.
 */
return new class extends Migration
{
    private const RESTRICCION = 'vacaciones_estado_check';

    public function up(): void
    {
        Schema::create('vacacion_descuentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacacion_id')->constrained('vacaciones')->cascadeOnDelete();
            // Sin cascada: borrar un período no puede llevarse en silencio el
            // rastro de lo que se descontó de él.
            $table->foreignId('periodo_vacacion_id')->constrained('periodos_vacaciones');
            $table->decimal('dias', 8, 2);
            $table->timestamp('devuelto_en')->nullable();
            $table->timestamps();

            $table->index(['vacacion_id', 'devuelto_en']);
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->text('motivo_anulacion')->nullable();
        });

        DB::statement('ALTER TABLE vacaciones DROP CONSTRAINT IF EXISTS '.self::RESTRICCION);
        DB::statement('ALTER TABLE vacaciones ADD CONSTRAINT '.self::RESTRICCION." CHECK (
            estado::text IN ('pendiente', 'aprobada', 'rechazada', 'gozada', 'anulada')
        )");
    }

    public function down(): void
    {
        // El CHECK estrecho no admite `anulada`: se reclasifican como
        // rechazadas, que es lo más cercano que existía.
        DB::table('vacaciones')->where('estado', 'anulada')->update(['estado' => 'rechazada']);

        DB::statement('ALTER TABLE vacaciones DROP CONSTRAINT IF EXISTS '.self::RESTRICCION);
        DB::statement('ALTER TABLE vacaciones ADD CONSTRAINT '.self::RESTRICCION." CHECK (
            estado::text IN ('pendiente', 'aprobada', 'rechazada', 'gozada')
        )");

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropForeign(['anulado_por']);
            $table->dropColumn(['anulado_por', 'anulado_en', 'motivo_anulacion']);
        });

        Schema::dropIfExists('vacacion_descuentos');
    }
};

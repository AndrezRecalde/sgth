<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anotaciones de una acción de personal (diseño de Acciones de Personal, 8.1;
 * fase 1.4).
 *
 * Desde que un acto está registrado no cambia ningún campo de contenido. Lo que
 * pasa después —la impugnación del visto bueno que originó una cesación, una
 * nota de Talento Humano— se anota aquí, aparte, en vez de reescribir el acto.
 *
 * Las impugnaciones que ya existen se copian desde `vistos_buenos`, que es
 * donde está el dato. El aviso que se añadió a la explicación de esas acciones
 * se deja como está: si la acción se registró después, ese texto es parte del
 * acto emitido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anotaciones_accion_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_personal_id')
                ->constrained('movimientos_personal')->cascadeOnDelete();
            $table->string('tipo', 40);
            $table->text('texto');
            // La impugnación nombra el visto bueno del que sale: es lo que evita
            // anotarla dos veces.
            $table->foreignId('visto_bueno_id')->nullable()
                ->constrained('vistos_buenos')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('movimiento_personal_id');
        });

        DB::statement("
            ALTER TABLE anotaciones_accion_personal
            ADD CONSTRAINT anotaciones_accion_personal_tipo_check
            CHECK (tipo IN ('impugnacion_visto_bueno', 'nota'))
        ");

        DB::statement("
            INSERT INTO anotaciones_accion_personal (
                movimiento_personal_id, tipo, texto, visto_bueno_id, created_at, updated_at
            )
            SELECT
                v.movimiento_personal_id,
                'impugnacion_visto_bueno',
                'Impugnado por el trabajador ('
                    || v.impugnacion_referencia
                    || COALESCE(', ' || to_char(v.fecha_impugnacion, 'DD/MM/YYYY'), '')
                    || '): revísese con Asesoría Jurídica antes de continuar con esta cesación.',
                v.id,
                COALESCE(v.updated_at, now()),
                COALESCE(v.updated_at, now())
            FROM vistos_buenos AS v
            WHERE v.impugnacion_referencia IS NOT NULL
              AND v.movimiento_personal_id IS NOT NULL
            ORDER BY v.id
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('anotaciones_accion_personal');
    }
};

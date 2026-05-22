<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Recalcula nivel recursivamente desde la raíz
        // Nivel 1 = raíz (GADPE, sin padre)
        // Nivel 2 = hijos directos del GADPE (gestiones)
        // Nivel 3 = subprocesos (hijos de las gestiones)

        // Paso 1: Raíz = nivel 1
        DB::statement("
            UPDATE unidades_administrativas
            SET nivel = 1
            WHERE unidad_padre_id IS NULL
              AND deleted_at IS NULL
        ");

        // Paso 2: Hijos de raíz = nivel 2
        DB::statement("
            UPDATE unidades_administrativas
            SET nivel = 2
            WHERE unidad_padre_id IN (
                SELECT id FROM (
                    SELECT id FROM unidades_administrativas
                    WHERE unidad_padre_id IS NULL
                      AND deleted_at IS NULL
                ) AS raiz
            )
            AND deleted_at IS NULL
        ");

        // Paso 3: Hijos de nivel 2 = nivel 3
        DB::statement("
            UPDATE unidades_administrativas
            SET nivel = 3
            WHERE unidad_padre_id IN (
                SELECT id FROM (
                    SELECT id FROM unidades_administrativas
                    WHERE nivel = 2
                      AND deleted_at IS NULL
                ) AS nivel2
            )
            AND deleted_at IS NULL
        ");

        // Paso 4: Hijos de nivel 3 = nivel 4 (por si hubiera)
        DB::statement("
            UPDATE unidades_administrativas
            SET nivel = 4
            WHERE unidad_padre_id IN (
                SELECT id FROM (
                    SELECT id FROM unidades_administrativas
                    WHERE nivel = 3
                      AND deleted_at IS NULL
                ) AS nivel3
            )
            AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE unidades_administrativas SET nivel = 1
            WHERE deleted_at IS NULL
        ");
    }
};

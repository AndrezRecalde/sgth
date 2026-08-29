<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula la ficha FEMO al puesto evaluado.
 *
 * Hasta ahora solo se guardaba `puesto_trabajo` como texto escrito por el
 * médico, aunque el puesto siempre existe como dato: en la convocatoria formal,
 * en el propio aspirante de reclutamiento express, o en el expediente del
 * servidor para las periódicas y los retiros.
 *
 * Se conservan las DOS cosas a propósito:
 *  - `puesto_id` para poder cruzar fichas con puestos, unidades y la matriz de
 *    riesgos del puesto;
 *  - `puesto_trabajo` y `puesto_trabajo_ciuo` como copia sellada al momento de
 *    la evaluación. Un cargo puede renombrarse o cambiar de código CIUO, y una
 *    ficha de 2026 debe seguir mostrando lo que decía entonces. Es el mismo
 *    criterio que ya se aplica a los firmantes de las acciones de personal.
 *
 * `restrictOnDelete` y no `nullOnDelete`: perder el vínculo dejaría la ficha
 * sin trazabilidad hacia la estructura, y un puesto con fichas médicas no
 * debería poder borrarse sin decidir antes qué pasa con ellas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->foreignId('puesto_id')
                ->nullable()
                ->after('postulante_id')
                ->constrained('puestos')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->dropConstrainedForeignId('puesto_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El catálogo de comprobantes se reduce a lo que Gestión Financiera reconoce.
 *
 * Confirmado el 2026-09-15: se justifican el hospedaje, la alimentación y la
 * movilización —pasajes, combustible de vehículos institucionales y peajes—.
 * El pasaje aéreo se queda porque lo compra el propio servidor: la institución
 * no compra pasajes.
 *
 * Materiales, comunicaciones, inscripción, visa, seguro de viaje y «Otro» no
 * son gastos que el viático cubra, así que dejan de ofrecerse. Se desactivan,
 * no se borran: hay liquidaciones antiguas que las usan y el comprobante
 * impreso tiene que seguir diciendo de qué era cada factura.
 */
return new class extends Migration
{
    private const SIN_USO = [
        'materiales', 'comunicaciones', 'inscripcion',
        'visa_tramite', 'seguro_viaje', 'otro',
    ];

    public function up(): void
    {
        DB::table('categorias_factura')
            ->whereIn('codigo', self::SIN_USO)
            ->update(['activo' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('categorias_factura')
            ->whereIn('codigo', self::SIN_USO)
            ->update(['activo' => true, 'updated_at' => now()]);
    }
};

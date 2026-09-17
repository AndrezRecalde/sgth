<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriaFacturaSeeder extends Seeder
{
    public function run(): void
    {
        // `grupo` decide qué justifica el 70 % del viático: solo hospedaje y
        // alimentación (`viatico`); lo demás es movilización u otro gasto. Sin
        // fijarlo, la columna tomaba `movilizacion` por defecto en todas.
        //
        // Solo se ofrecen las categorías que Gestión Financiera reconoce
        // (2026-09-15): hospedaje, alimentación y la movilización. El pasaje
        // aéreo se queda porque lo compra el propio servidor.
        //
        // Nacen inactivas «Viático diario», que no es un gasto con factura
        // propia, y las que el viático no cubre: materiales, comunicaciones,
        // inscripción, visa, seguro y «Otro». Se siembran igual para que las
        // liquidaciones antiguas sigan diciendo de qué era cada factura.
        $categorias = [
            ['nombre' => 'Hospedaje',                'codigo' => 'hospedaje',            'grupo' => 'viatico',      'orden' => 1],
            ['nombre' => 'Alimentación',             'codigo' => 'alimentacion',          'grupo' => 'viatico',      'orden' => 2],
            ['nombre' => 'Transporte terrestre',     'codigo' => 'transporte_terrestre',  'grupo' => 'movilizacion', 'orden' => 3],
            ['nombre' => 'Pasaje aéreo',             'codigo' => 'pasaje_aereo',          'grupo' => 'movilizacion', 'orden' => 4],
            ['nombre' => 'Combustible',              'codigo' => 'combustible',           'grupo' => 'movilizacion', 'orden' => 5],
            ['nombre' => 'Peaje',                    'codigo' => 'peaje',                 'grupo' => 'movilizacion', 'orden' => 6],
            ['nombre' => 'Viático diario',           'codigo' => 'viatico_diario',        'grupo' => 'movilizacion', 'orden' => 7, 'activo' => false],
            ['nombre' => 'Materiales / Suministros', 'codigo' => 'materiales',            'grupo' => 'movilizacion', 'orden' => 8, 'activo' => false],
            ['nombre' => 'Comunicaciones',           'codigo' => 'comunicaciones',        'grupo' => 'movilizacion', 'orden' => 9, 'activo' => false],
            ['nombre' => 'Inscripción / Registro',   'codigo' => 'inscripcion',           'grupo' => 'movilizacion', 'orden' => 10, 'activo' => false],
            ['nombre' => 'Visa / Trámite migratorio','codigo' => 'visa_tramite',          'grupo' => 'movilizacion', 'orden' => 11, 'activo' => false],
            ['nombre' => 'Seguro de viaje',          'codigo' => 'seguro_viaje',          'grupo' => 'movilizacion', 'orden' => 12, 'activo' => false],
            ['nombre' => 'Otro',                     'codigo' => 'otro',                  'grupo' => 'movilizacion', 'orden' => 99, 'activo' => false],
        ];

        foreach ($categorias as $cat) {
            DB::table('categorias_factura')->insertOrIgnore([
                'activo'     => true,
                ...$cat,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command?->info('Categorías de factura sembradas: ' . count($categorias));
    }
}

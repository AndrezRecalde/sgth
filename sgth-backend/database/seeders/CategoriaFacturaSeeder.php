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
        // «Viático diario» nace inactiva: no es un gasto con factura propia.
        $categorias = [
            ['nombre' => 'Hospedaje',                'codigo' => 'hospedaje',            'grupo' => 'viatico',      'orden' => 1],
            ['nombre' => 'Alimentación',             'codigo' => 'alimentacion',          'grupo' => 'viatico',      'orden' => 2],
            ['nombre' => 'Transporte terrestre',     'codigo' => 'transporte_terrestre',  'grupo' => 'movilizacion', 'orden' => 3],
            ['nombre' => 'Pasaje aéreo',             'codigo' => 'pasaje_aereo',          'grupo' => 'movilizacion', 'orden' => 4],
            ['nombre' => 'Combustible',              'codigo' => 'combustible',           'grupo' => 'movilizacion', 'orden' => 5],
            ['nombre' => 'Peaje',                    'codigo' => 'peaje',                 'grupo' => 'movilizacion', 'orden' => 6],
            ['nombre' => 'Viático diario',           'codigo' => 'viatico_diario',        'grupo' => 'movilizacion', 'orden' => 7, 'activo' => false],
            ['nombre' => 'Materiales / Suministros', 'codigo' => 'materiales',            'grupo' => 'movilizacion', 'orden' => 8],
            ['nombre' => 'Comunicaciones',           'codigo' => 'comunicaciones',        'grupo' => 'movilizacion', 'orden' => 9],
            ['nombre' => 'Inscripción / Registro',   'codigo' => 'inscripcion',           'grupo' => 'movilizacion', 'orden' => 10],
            ['nombre' => 'Visa / Trámite migratorio','codigo' => 'visa_tramite',          'grupo' => 'movilizacion', 'orden' => 11],
            ['nombre' => 'Seguro de viaje',          'codigo' => 'seguro_viaje',          'grupo' => 'movilizacion', 'orden' => 12],
            ['nombre' => 'Otro',                     'codigo' => 'otro',                  'grupo' => 'movilizacion', 'orden' => 99],
        ];

        foreach ($categorias as $cat) {
            DB::table('categorias_factura')->insertOrIgnore([
                'activo'     => true,
                ...$cat,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Categorías de factura sembradas: ' . count($categorias));
    }
}

<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriaFacturaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Hospedaje',                'codigo' => 'hospedaje',            'orden' => 1],
            ['nombre' => 'Alimentación',             'codigo' => 'alimentacion',          'orden' => 2],
            ['nombre' => 'Transporte terrestre',     'codigo' => 'transporte_terrestre',  'orden' => 3],
            ['nombre' => 'Pasaje aéreo',             'codigo' => 'pasaje_aereo',          'orden' => 4],
            ['nombre' => 'Combustible',              'codigo' => 'combustible',           'orden' => 5],
            ['nombre' => 'Peaje',                    'codigo' => 'peaje',                 'orden' => 6],
            ['nombre' => 'Viático diario',           'codigo' => 'viatico_diario',        'orden' => 7],
            ['nombre' => 'Materiales / Suministros', 'codigo' => 'materiales',            'orden' => 8],
            ['nombre' => 'Comunicaciones',           'codigo' => 'comunicaciones',        'orden' => 9],
            ['nombre' => 'Inscripción / Registro',   'codigo' => 'inscripcion',           'orden' => 10],
            ['nombre' => 'Visa / Trámite migratorio','codigo' => 'visa_tramite',          'orden' => 11],
            ['nombre' => 'Seguro de viaje',          'codigo' => 'seguro_viaje',          'orden' => 12],
            ['nombre' => 'Otro',                     'codigo' => 'otro',                  'orden' => 99],
        ];

        foreach ($categorias as $cat) {
            DB::table('categorias_factura')->insertOrIgnore([
                ...$cat,
                'activo'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Categorías de factura sembradas: ' . count($categorias));
    }
}

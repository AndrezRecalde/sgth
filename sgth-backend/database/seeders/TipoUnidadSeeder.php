<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Estructura\TipoUnidad;

class TipoUnidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            [
                'id' => '11111111-1111-1111-1111-111111111111',
                'acronimo' => 'G',
                'descripcion' => 'GOBERNANTES',
            ],
            [
                'id' => '22222222-2222-2222-2222-222222222222',
                'acronimo' => 'HAP',
                'descripcion' => 'HABILITANTES DE APOYO',
            ],
            [
                'id' => '33333333-3333-3333-3333-333333333333',
                'acronimo' => 'HA',
                'descripcion' => 'HABILITANTES ASESORES',
            ],
            [
                'id' => '44444444-4444-4444-4444-444444444444',
                'acronimo' => 'AV',
                'descripcion' => 'AGREGADORES DE VALOR',
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoUnidad::firstOrCreate(
                ['id' => $tipo['id']],
                [
                    'acronimo' => $tipo['acronimo'],
                    'descripcion' => $tipo['descripcion'],
                ]
            );
        }
    }
}

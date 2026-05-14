<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Catalogo\EntidadFinanciera;

class EntidadFinancieraSeeder extends Seeder
{
    public function run(): void
    {
        $bancos = [
            'Banco del Pacífico',
            'Banco Pichincha',
            'Produbanco',
            'Banco de Guayaquil',
            'Banco Central del Ecuador',
            'Banco del Austro',
            'Banco Bolivariano',
            'Banco Internacional',
            'Banco Solidario',
        ];

        $cooperativas = [
            'JEP Cooperativa',
            'Coopmego',
            '29 de Octubre Cooperativa',
            'Jardín Azuayo',
        ];

        foreach ($bancos as $banco) {
            EntidadFinanciera::firstOrCreate(
                ['nombre' => $banco],
                ['tipo' => 'banco', 'estado' => true]
            );
        }

        foreach ($cooperativas as $coop) {
            EntidadFinanciera::firstOrCreate(
                ['nombre' => $coop],
                ['tipo' => 'cooperativa', 'estado' => true]
            );
        }
    }
}

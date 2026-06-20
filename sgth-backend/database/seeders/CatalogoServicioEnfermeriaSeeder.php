<?php

namespace Database\Seeders;

use App\Models\Dispensario\CatalogoServicioEnfermeria;
use Illuminate\Database\Seeder;

class CatalogoServicioEnfermeriaSeeder extends Seeder
{
    public function run(): void
    {
        $servicios = [
            'Inyección intramuscular',
            'Inyección intravenosa',
            'Curación de heridas',
            'Control de signos vitales',
            'Nebulización',
            'Toma de glucosa',
            'Vacunación',
            'Retiro de puntos',
            'Vendaje / Inmovilización',
            'Otro procedimiento',
        ];

        foreach ($servicios as $nombre) {
            CatalogoServicioEnfermeria::firstOrCreate([
                'nombre' => $nombre,
            ]);
        }
    }
}

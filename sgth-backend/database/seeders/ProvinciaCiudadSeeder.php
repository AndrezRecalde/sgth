<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Geografia\Provincia;
use App\Models\Geografia\Ciudad;

class ProvinciaCiudadSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['codigo' => 'AZ', 'nombre' => 'Azuay', 'ciudades' => ['Cuenca', 'Gualaceo', 'Paute']],
            ['codigo' => 'BO', 'nombre' => 'Bolívar', 'ciudades' => ['Guaranda', 'San Miguel', 'Chillanes']],
            ['codigo' => 'CA', 'nombre' => 'Cañar', 'ciudades' => ['Azogues', 'Cañar', 'La Troncal']],
            ['codigo' => 'CR', 'nombre' => 'Carchi', 'ciudades' => ['Tulcán', 'San Gabriel', 'El Ángel']],
            ['codigo' => 'CH', 'nombre' => 'Chimborazo', 'ciudades' => ['Riobamba', 'Guano', 'Alausí']],
            ['codigo' => 'CO', 'nombre' => 'Cotopaxi', 'ciudades' => ['Latacunga', 'Salcedo', 'Pujilí']],
            ['codigo' => 'EL', 'nombre' => 'El Oro', 'ciudades' => ['Machala', 'Pasaje', 'Santa Rosa']],
            ['codigo' => 'ES', 'nombre' => 'Esmeraldas', 'ciudades' => ['Esmeraldas', 'Quinindé', 'Atacames']],
            ['codigo' => 'GB', 'nombre' => 'Galápagos', 'ciudades' => ['Puerto Baquerizo Moreno', 'Puerto Ayora', 'Puerto Villamil']],
            ['codigo' => 'GU', 'nombre' => 'Guayas', 'ciudades' => ['Guayaquil', 'Durán', 'Milagro']],
            ['codigo' => 'IM', 'nombre' => 'Imbabura', 'ciudades' => ['Ibarra', 'Otavalo', 'Cotacachi']],
            ['codigo' => 'LO', 'nombre' => 'Loja', 'ciudades' => ['Loja', 'Catamayo', 'Macará']],
            ['codigo' => 'RO', 'nombre' => 'Los Ríos', 'ciudades' => ['Babahoyo', 'Quevedo', 'Ventanas']],
            ['codigo' => 'MN', 'nombre' => 'Manabí', 'ciudades' => ['Portoviejo', 'Manta', 'Chone']],
            ['codigo' => 'MR', 'nombre' => 'Morona Santiago', 'ciudades' => ['Macas', 'Sucúa', 'Gualaquiza']],
            ['codigo' => 'NA', 'nombre' => 'Napo', 'ciudades' => ['Tena', 'Archidona', 'El Chaco']],
            ['codigo' => 'OE', 'nombre' => 'Orellana', 'ciudades' => ['Puerto Francisco de Orellana', 'La Joya de los Sachas', 'Loreto']],
            ['codigo' => 'PA', 'nombre' => 'Pastaza', 'ciudades' => ['Puyo', 'Mera', 'Santa Clara']],
            ['codigo' => 'PI', 'nombre' => 'Pichincha', 'ciudades' => ['Quito', 'Sangolquí', 'Machachi']],
            ['codigo' => 'SA', 'nombre' => 'Santa Elena', 'ciudades' => ['Santa Elena', 'La Libertad', 'Salinas']],
            ['codigo' => 'SD', 'nombre' => 'Santo Domingo', 'ciudades' => ['Santo Domingo', 'La Concordia']],
            ['codigo' => 'SU', 'nombre' => 'Sucumbíos', 'ciudades' => ['Nueva Loja', 'Shushufindi', 'Cuyabeno']],
            ['codigo' => 'TU', 'nombre' => 'Tungurahua', 'ciudades' => ['Ambato', 'Baños', 'Pelileo']],
            ['codigo' => 'ZC', 'nombre' => 'Zamora Chinchipe', 'ciudades' => ['Zamora', 'Yantzaza', 'Zumba']],
        ];

        foreach ($data as $item) {
            $provincia = Provincia::firstOrCreate(
                ['codigo' => $item['codigo']],
                ['nombre' => $item['nombre']]
            );

            foreach ($item['ciudades'] as $ciudadNombre) {
                Ciudad::firstOrCreate(
                    ['provincia_id' => $provincia->id, 'nombre' => $ciudadNombre]
                );
            }
        }
    }
}

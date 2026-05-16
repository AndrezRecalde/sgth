<?php

namespace Database\Seeders;

use App\Models\Geografia\Canton;
use App\Models\Geografia\Provincia;
use Illuminate\Database\Seeder;

class ProvinciaCantonSeeder extends Seeder
{
    public function run(): void
    {
        $provincias = [
            ['codigo' => 'AZ', 'nombre' => 'Azuay', 'cantones' => ['Cuenca', 'Girón', 'Gualaceo', 'Nabón', 'Pucará', 'San Fernando', 'Santa Isabel', 'Sigsig', 'Oña', 'Chordeleg', 'El Pan', 'Sevilla de Oro', 'Guachapala', 'Camilo Ponce Enríquez']],
            ['codigo' => 'BO', 'nombre' => 'Bolívar', 'cantones' => ['Guaranda', 'Chillanes', 'Chimbo', 'Echeandía', 'San Miguel', 'Caluma', 'Las Naves']],
            ['codigo' => 'CA', 'nombre' => 'Cañar', 'cantones' => ['Azogues', 'Biblián', 'Cañar', 'La Troncal', 'El Tambo', 'Déleg', 'Suscal']],
            ['codigo' => 'CR', 'nombre' => 'Carchi', 'cantones' => ['Tulcán', 'Bolívar', 'Espejo', 'Mira', 'Montúfar', 'San Pedro de Huaca']],
            ['codigo' => 'CH', 'nombre' => 'Chimborazo', 'cantones' => ['Riobamba', 'Alausí', 'Colta', 'Chambo', 'Chunchi', 'Guamote', 'Guano', 'Pallatanga', 'Penipe', 'Cumandá']],
            ['codigo' => 'CO', 'nombre' => 'Cotopaxi', 'cantones' => ['Latacunga', 'La Maná', 'Pangua', 'Pujilí', 'Salcedo', 'Saquisilí', 'Sigchos']],
            ['codigo' => 'EL', 'nombre' => 'El Oro', 'cantones' => ['Machala', 'Arenillas', 'Atahualpa', 'Balsas', 'Chilla', 'El Guabo', 'Huaquillas', 'Marcabelí', 'Pasaje', 'Piñas', 'Portovelo', 'Santa Rosa', 'Zaruma', 'Las Lajas']],
            ['codigo' => 'ES', 'nombre' => 'Esmeraldas', 'cantones' => ['Esmeraldas', 'Atacames', 'Eloy Alfaro', 'Muisne', 'Quinindé', 'San Lorenzo', 'Río Verde', 'Rioverde']],
            ['codigo' => 'GB', 'nombre' => 'Galápagos', 'cantones' => ['San Cristóbal', 'Isabela', 'Santa Cruz']],
            ['codigo' => 'GU', 'nombre' => 'Guayas', 'cantones' => ['Guayaquil', 'Alfredo Baquerizo Moreno', 'Balao', 'Balzar', 'Colimes', 'Coronel Marcelino Maridueña', 'Daule', 'Durán', 'El Empalme', 'El Triunfo', 'General Antonio Elizalde', 'Isidro Ayora', 'Lomas de Sargentillo', 'Milagro', 'Naranjal', 'Naranjito', 'Nobol', 'Palestina', 'Pedro Carbo', 'Playas', 'Salitre', 'Samborondón', 'Santa Lucía', 'Simón Bolívar', 'Yaguachi']],
            ['codigo' => 'IM', 'nombre' => 'Imbabura', 'cantones' => ['Ibarra', 'Antonio Ante', 'Cotacachi', 'Otavalo', 'Pimampiro', 'San Miguel de Urcuquí']],
            ['codigo' => 'LO', 'nombre' => 'Loja', 'cantones' => ['Loja', 'Calvas', 'Catamayo', 'Celica', 'Chaguarpamba', 'Espíndola', 'Gonzanamá', 'Macará', 'Paltas', 'Puyango', 'Quilanga', 'Saraguro', 'Sozoranga', 'Zapotillo', 'Pindal', 'Olmedo', 'Centinela del Cóndor']],
            ['codigo' => 'RO', 'nombre' => 'Los Ríos', 'cantones' => ['Babahoyo', 'Baba', 'Buena Fe', 'Mocache', 'Montalvo', 'Palenque', 'Pueblo Viejo', 'Quevedo', 'Quinsaloma', 'Urdaneta', 'Valencia', 'Ventanas', 'Vinces']],
            ['codigo' => 'MN', 'nombre' => 'Manabí', 'cantones' => ['Portoviejo', 'Bolívar', 'Chone', 'El Carmen', 'Flavio Alfaro', 'Jama', 'Jaramijó', 'Jipijapa', 'Junín', 'Manta', 'Montecristi', 'Olmedo', 'Paján', 'Pedernales', 'Pichincha', 'Puerto López', 'Rocafuerte', 'San Vicente', 'Santa Ana', 'Sucre', 'Tosagua', '24 de Mayo']],
            ['codigo' => 'MR', 'nombre' => 'Morona Santiago', 'cantones' => ['Macas', 'Gualaquiza', 'Huamboya', 'Limón Indanza', 'Logroño', 'Morona', 'Pablo Sexto', 'Palora', 'San Juan Bosco', 'Santiago', 'Sucúa', 'Taisha', 'Tiwintza']],
            ['codigo' => 'NA', 'nombre' => 'Napo', 'cantones' => ['Tena', 'Archidona', 'El Chaco', 'Quijos', 'Carlos Julio Arosemena Tola']],
            ['codigo' => 'OE', 'nombre' => 'Orellana', 'cantones' => ['Francisco de Orellana', 'Aguarico', 'La Joya de los Sachas', 'Loreto']],
            ['codigo' => 'PA', 'nombre' => 'Pastaza', 'cantones' => ['Puyo', 'Arajuno', 'Mera', 'Santa Clara']],
            ['codigo' => 'PI', 'nombre' => 'Pichincha', 'cantones' => ['Quito', 'Cayambe', 'Mejía', 'Pedro Moncayo', 'Rumiñahui', 'San Miguel de los Bancos', 'Pedro Vicente Maldonado', 'Puerto Quito']],
            ['codigo' => 'SA', 'nombre' => 'Santa Elena', 'cantones' => ['Santa Elena', 'La Libertad', 'Salinas']],
            ['codigo' => 'SD', 'nombre' => 'Santo Domingo', 'cantones' => ['Santo Domingo', 'La Concordia']],
            ['codigo' => 'SU', 'nombre' => 'Sucumbíos', 'cantones' => ['Nueva Loja', 'Cascales', 'Cuyabeno', 'Gonzalo Pizarro', 'Putumayo', 'Shushufindi', 'Sucumbíos']],
            ['codigo' => 'TU', 'nombre' => 'Tungurahua', 'cantones' => ['Ambato', 'Baños de Agua Santa', 'Cevallos', 'Mocha', 'Patate', 'Quero', 'San Pedro de Pelileo', 'Santiago de Píllaro', 'Tisaleo']],
            ['codigo' => 'ZC', 'nombre' => 'Zamora Chinchipe', 'cantones' => ['Zamora', 'Chinchipe', 'Centinela del Cóndor', 'Palanda', 'Paquisha', 'Nangaritza', 'Yacuambi', 'Yantzaza', 'El Pangui']],
        ];

        foreach ($provincias as $item) {
            $provincia = Provincia::firstOrCreate(
                ['codigo' => $item['codigo']],
                ['nombre' => $item['nombre']]
            );

            foreach ($item['cantones'] as $cantonNombre) {
                Canton::firstOrCreate([
                    'provincia_id' => $provincia->id,
                    'nombre' => $cantonNombre,
                ]);
            }
        }
    }
}

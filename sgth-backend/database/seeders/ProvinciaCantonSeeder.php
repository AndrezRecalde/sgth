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
            'Azuay' => ['Cuenca', 'Girón', 'Gualaceo', 'Nabón', 'Pucará', 'San Fernando', 'Santa Isabel', 'Sigsig', 'Oña', 'Chordeleg', 'El Pan', 'Sevilla de Oro', 'Guachapala', 'Camilo Ponce Enríquez'],
            'Bolívar' => ['Guaranda', 'Chillanes', 'Chimbo', 'Echeandía', 'San Miguel', 'Caluma', 'Las Naves'],
            'Cañar' => ['Azogues', 'Biblián', 'Cañar', 'La Troncal', 'El Tambo', 'Déleg', 'Suscal'],
            'Carchi' => ['Tulcán', 'Bolívar', 'Espejo', 'Mira', 'Montúfar', 'San Pedro de Huaca'],
            'Chimborazo' => ['Riobamba', 'Alausí', 'Colta', 'Chambo', 'Chunchi', 'Guamote', 'Guano', 'Pallatanga', 'Penipe', 'Cumandá'],
            'Cotopaxi' => ['Latacunga', 'La Maná', 'Pangua', 'Pujilí', 'Salcedo', 'Saquisilí', 'Sigchos'],
            'El Oro' => ['Machala', 'Arenillas', 'Atahualpa', 'Balsas', 'Chilla', 'El Guabo', 'Huaquillas', 'Marcabelí', 'Pasaje', 'Piñas', 'Portovelo', 'Santa Rosa', 'Zaruma', 'Las Lajas'],
            'Esmeraldas' => ['Esmeraldas', 'Atacames', 'Eloy Alfaro', 'Muisne', 'Quinindé', 'San Lorenzo', 'Río Verde', 'Rioverde'],
            'Galápagos' => ['San Cristóbal', 'Isabela', 'Santa Cruz'],
            'Guayas' => ['Guayaquil', 'Alfredo Baquerizo Moreno', 'Balao', 'Balzar', 'Colimes', 'Coronel Marcelino Maridueña', 'Daule', 'Durán', 'El Empalme', 'El Triunfo', 'General Antonio Elizalde', 'Isidro Ayora', 'Lomas de Sargentillo', 'Milagro', 'Naranjal', 'Naranjito', 'Nobol', 'Palestina', 'Pedro Carbo', 'Playas', 'Salitre', 'Samborondón', 'Santa Lucía', 'Simón Bolívar', 'Yaguachi'],
            'Imbabura' => ['Ibarra', 'Antonio Ante', 'Cotacachi', 'Otavalo', 'Pimampiro', 'San Miguel de Urcuquí'],
            'Loja' => ['Loja', 'Calvas', 'Catamayo', 'Celica', 'Chaguarpamba', 'Espíndola', 'Gonzanamá', 'Macará', 'Paltas', 'Puyango', 'Quilanga', 'Saraguro', 'Sozoranga', 'Zapotillo', 'Pindal', 'Olmedo', 'Centinela del Cóndor'],
            'Los Ríos' => ['Babahoyo', 'Baba', 'Buena Fe', 'Mocache', 'Montalvo', 'Palenque', 'Pueblo Viejo', 'Quevedo', 'Quinsaloma', 'Urdaneta', 'Valencia', 'Ventanas', 'Vinces'],
            'Manabí' => ['Portoviejo', 'Bolívar', 'Chone', 'El Carmen', 'Flavio Alfaro', 'Jama', 'Jaramijó', 'Jipijapa', 'Junín', 'Manta', 'Montecristi', 'Olmedo', 'Paján', 'Pedernales', 'Pichincha', 'Puerto López', 'Rocafuerte', 'San Vicente', 'Santa Ana', 'Sucre', 'Tosagua', '24 de Mayo'],
            'Morona Santiago' => ['Macas', 'Gualaquiza', 'Huamboya', 'Limón Indanza', 'Logroño', 'Morona', 'Pablo Sexto', 'Palora', 'San Juan Bosco', 'Santiago', 'Sucúa', 'Taisha', 'Tiwintza'],
            'Napo' => ['Tena', 'Archidona', 'El Chaco', 'Quijos', 'Carlos Julio Arosemena Tola'],
            'Orellana' => ['Francisco de Orellana', 'Aguarico', 'La Joya de los Sachas', 'Loreto'],
            'Pastaza' => ['Puyo', 'Arajuno', 'Mera', 'Santa Clara'],
            'Pichincha' => ['Quito', 'Cayambe', 'Mejía', 'Pedro Moncayo', 'Rumiñahui', 'San Miguel de los Bancos', 'Pedro Vicente Maldonado', 'Puerto Quito'],
            'Santa Elena' => ['Santa Elena', 'La Libertad', 'Salinas'],
            'Santo Domingo' => ['Santo Domingo', 'La Concordia'],
            'Sucumbíos' => ['Nueva Loja', 'Cascales', 'Cuyabeno', 'Gonzalo Pizarro', 'Putumayo', 'Shushufindi', 'Sucumbíos'],
            'Tungurahua' => ['Ambato', 'Baños de Agua Santa', 'Cevallos', 'Mocha', 'Patate', 'Quero', 'San Pedro de Pelileo', 'Santiago de Píllaro', 'Tisaleo'],
            'Zamora Chinchipe' => ['Zamora', 'Chinchipe', 'Centinela del Cóndor', 'Palanda', 'Paquisha', 'Nangaritza', 'Yacuambi', 'Yantzaza', 'El Pangui'],
        ];

        foreach ($provincias as $provinciaNombre => $cantones) {
            $provincia = Provincia::firstOrCreate(['nombre' => $provinciaNombre]);

            foreach ($cantones as $cantonNombre) {
                Canton::firstOrCreate([
                    'provincia_id' => $provincia->id,
                    'nombre' => $cantonNombre,
                ]);
            }
        }
    }
}

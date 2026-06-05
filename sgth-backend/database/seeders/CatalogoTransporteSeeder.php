<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoTransporteSeeder extends Seeder
{
    public function run(): void
    {
        // Tipos de transporte
        $tipos = [
            ['nombre' => 'Bus interprovincial', 'codigo' => 'bus_interprovincial',
             'tipo_vehiculo' => 'terrestre', 'requiere_autorizacion' => false, 'orden' => 1],
            ['nombre' => 'Bus internacional',   'codigo' => 'bus_internacional',
             'tipo_vehiculo' => 'terrestre', 'requiere_autorizacion' => false, 'orden' => 2],
            ['nombre' => 'Avión nacional',       'codigo' => 'avion_nacional',
             'tipo_vehiculo' => 'aereo',     'requiere_autorizacion' => true,  'orden' => 3],
            ['nombre' => 'Avión internacional',  'codigo' => 'avion_internacional',
             'tipo_vehiculo' => 'aereo',     'requiere_autorizacion' => true,  'orden' => 4],
            ['nombre' => 'Vehículo institucional','codigo' => 'vehiculo_institucional',
             'tipo_vehiculo' => 'terrestre', 'requiere_autorizacion' => false, 'orden' => 5],
            ['nombre' => 'Vehículo particular',  'codigo' => 'vehiculo_particular',
             'tipo_vehiculo' => 'terrestre', 'requiere_autorizacion' => false, 'orden' => 6],
            ['nombre' => 'Taxi / Uber',          'codigo' => 'taxi',
             'tipo_vehiculo' => 'terrestre', 'requiere_autorizacion' => false, 'orden' => 7],
            ['nombre' => 'Barco / Lancha',       'codigo' => 'barco',
             'tipo_vehiculo' => 'maritimo',  'requiere_autorizacion' => false, 'orden' => 8],
            ['nombre' => 'Otros',                'codigo' => 'otros',
             'tipo_vehiculo' => 'otro',      'requiere_autorizacion' => false, 'orden' => 9],
        ];

        foreach ($tipos as $tipo) {
            DB::table('catalogo_transportes')->insertOrIgnore([
                ...$tipo,
                'activo'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Empresas por tipo
        $busId  = DB::table('catalogo_transportes')
            ->where('codigo', 'bus_interprovincial')->value('id');
        $busIntId = DB::table('catalogo_transportes')
            ->where('codigo', 'bus_internacional')->value('id');
        $avNacId = DB::table('catalogo_transportes')
            ->where('codigo', 'avion_nacional')->value('id');
        $avIntId = DB::table('catalogo_transportes')
            ->where('codigo', 'avion_internacional')->value('id');

        $empresas = [
            // Bus interprovincial Ecuador
            [$busId, 'Cooperativa Kennedy',            'kennedy',          1],
            [$busId, 'Cooperativa Occidentales',       'occidentales',     2],
            [$busId, 'Cooperativa Flota Imbabura',     'flota_imbabura',   3],
            [$busId, 'Cooperativa Panamericana',       'panamericana',     4],
            [$busId, 'Cooperativa Esmeraldas',         'esmeraldas',       5],
            [$busId, 'Cooperativa Trans Esmeraldas',   'trans_esmeraldas', 6],
            [$busId, 'Cooperativa Carlos Aray',        'carlos_aray',      7],
            [$busId, 'Cooperativa Ejecutivo',          'ejecutivo',        8],
            [$busId, 'Cooperativa Reina del Camino',   'reina_camino',     9],
            [$busId, 'Cooperativa Pullman Carchi',     'pullman_carchi',   10],
            [$busId, 'Cooperativa Trans Guayas',       'trans_guayas',     11],
            [$busId, 'Cooperativa Rutas Orenses',      'rutas_orenses',    12],
            [$busId, 'Cooperativa Patria',             'patria',           13],
            [$busId, 'Cooperativa 10 de Agosto',       'diez_agosto',      14],
            [$busId, 'Cooperativa Zaracay',            'zaracay',          15],
            [$busId, 'Otra cooperativa',               'otra_bus',         99],
            // Bus internacional
            [$busIntId, 'Ormeño',                      'ormeno',           1],
            [$busIntId, 'Rutas de América',            'rutas_america',    2],
            [$busIntId, 'Otra empresa internacional',  'otra_bus_int',     99],
            // Avión nacional
            [$avNacId, 'LATAM Ecuador',                'latam_ec',         1],
            [$avNacId, 'Avianca Ecuador',              'avianca_ec',       2],
            [$avNacId, 'Jetsmart Ecuador',             'jetsmart_ec',      3],
            [$avNacId, 'Asfluvia',                     'asfluvia',         4],
            [$avNacId, 'Fly Latam',                    'fly_latam',        5],
            // Avión internacional
            [$avIntId, 'LATAM',                        'latam',            1],
            [$avIntId, 'Avianca',                      'avianca',          2],
            [$avIntId, 'American Airlines',            'american',         3],
            [$avIntId, 'Copa Airlines',                'copa',             4],
            [$avIntId, 'Iberia',                       'iberia',           5],
            [$avIntId, 'Air France',                   'air_france',       6],
            [$avIntId, 'Emirates',                     'emirates',         7],
            [$avIntId, 'Jetsmart',                     'jetsmart',         8],
            [$avIntId, 'Wingo',                        'wingo',            9],
            [$avIntId, 'Sky Airline',                  'sky',              10],
            [$avIntId, 'Otra aerolínea',               'otra_aerolinea',   99],
        ];

        foreach ($empresas as [$tipoId, $nombre, $codigo, $orden]) {
            DB::table('empresas_transporte')->insertOrIgnore([
                'catalogo_transporte_id' => $tipoId,
                'nombre'     => $nombre,
                'codigo'     => $codigo,
                'orden'      => $orden,
                'activo'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Catálogo de transportes y empresas sembrado.');
    }
}

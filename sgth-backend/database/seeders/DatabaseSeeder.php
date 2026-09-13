<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolPermisoSeeder::class,
            AdminTiSeeder::class,
            ProvinciaCantonSeeder::class,
            EntidadFinancieraSeeder::class,
            TipoUnidadSeeder::class,
            GrupoOcupacionalSeeder::class,
            UnidadAdministrativaSeeder::class,
            PartidaPresupuestariaSeeder::class,
            ProgramaDrogasActividadesSeeder::class,
            ConfiguracionReporteMovimientoSeeder::class,
            ContenedorExpressSeeder::class,
            AnclajeFirmantesSeeder::class,

            // Viáticos. Sin estos catálogos no se puede solicitar un viático
            // (sin tarifas no hay monto), armar el itinerario ni registrar
            // comprobantes. Los tres se pueden volver a correr sin pisar lo
            // que ya existe.
            TarifaViaticoSeeder::class,
            CatalogoTransporteSeeder::class,
            CategoriaFacturaSeeder::class,

            // Catálogo CIE-10 (ejecutar manualmente: php artisan db:seed --class=Cie10Seeder)
            // Cie10Seeder::class,
        ]);
    }
}

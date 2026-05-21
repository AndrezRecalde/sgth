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
            
            // Catálogo CIE-10 (ejecutar manualmente: php artisan db:seed --class=Cie10Seeder)
            // Cie10Seeder::class,
        ]);
    }
}

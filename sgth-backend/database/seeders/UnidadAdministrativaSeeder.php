<?php

namespace Database\Seeders;

use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Seeder;

class UnidadAdministrativaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Nivel 1: Raíz institucional
        $raiz = UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'GAD-01'],
            [
                'nombre'          => 'Gobierno Autónomo Descentralizado Provincial de Esmeraldas',
                'descripcion'     => 'Máxima entidad ejecutiva de la provincia',
                'unidad_padre_id' => null,
                'nivel'           => 1,
                'estado'          => true,
            ]
        );

        // Nivel 2: Direcciones o Coordinaciones Generales
        $direccionTic = UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'DIR-TIC'],
            [
                'nombre'          => 'Dirección de Gestión de Tecnologías de la Información y Comunicación (DTIC)',
                'descripcion'     => 'Encargada del desarrollo de software, infraestructura y soporte',
                'unidad_padre_id' => $raiz->id,
                'nivel'           => 2,
                'estado'          => true,
            ]
        );

        $direccionUath = UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'DIR-UATH'],
            [
                'nombre'          => 'Dirección de Administración de Talento Humano',
                'descripcion'     => 'Unidad Administrativa de Talento Humano (UATH)',
                'unidad_padre_id' => $raiz->id,
                'nivel'           => 2,
                'estado'          => true,
            ]
        );

        // Nivel 3: Subprocesos
        UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'SUB-SISTEMAS'],
            [
                'nombre'          => 'Subproceso de Desarrollo de Sistemas',
                'descripcion'     => 'Equipo de ingeniería y arquitectura de software',
                'unidad_padre_id' => $direccionTic->id,
                'nivel'           => 3,
                'estado'          => true,
            ]
        );

        UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'SUB-SOPORTE'],
            [
                'nombre'          => 'Subproceso de Soporte Técnico y Redes',
                'descripcion'     => 'Mesa de ayuda y mantenimiento de infraestructura',
                'unidad_padre_id' => $direccionTic->id,
                'nivel'           => 3,
                'estado'          => true,
            ]
        );

        UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'SUB-NOMINA'],
            [
                'nombre'          => 'Subproceso de Nómina',
                'descripcion'     => 'Liquidación de haberes, viáticos y roles de pago',
                'unidad_padre_id' => $direccionUath->id,
                'nivel'           => 3,
                'estado'          => true,
            ]
        );
    }
}

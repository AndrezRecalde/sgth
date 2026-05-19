<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Estructura\UnidadAdministrativa;

class UnidadAdministrativaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
         * LÓGICA DE NIVELES JERÁRQUICOS:
         * Nivel 1: Raíz institucional (Prefectura, Viceprefectura). No tienen padre.
         * Nivel 2: Direcciones o Coordinaciones Generales (Gestiones). Dependen de la raíz.
         * Nivel 3: Subprocesos (Jefaturas, Unidades internas). Dependen de un Nivel 2.
         */

        // ─────────────────────────────────────────────────────────
        // NIVEL 1: Raíz Institucional
        // ─────────────────────────────────────────────────────────
        $prefectura = UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'PR'],
            [
                'nombre' => 'PREFECTURA',
                'tipo_unidad_id' => '11111111-1111-1111-1111-111111111111',
                'nivel' => 1,
                'unidad_padre_id' => null,
            ]
        );

        $viceprefectura = UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'VP'],
            [
                'nombre' => 'VICEPREFECTURA',
                'tipo_unidad_id' => '11111111-1111-1111-1111-111111111111',
                'nivel' => 1,
                'unidad_padre_id' => null,
            ]
        );

        // ─────────────────────────────────────────────────────────
        // NIVEL 2: Direcciones o Coordinaciones Generales
        // Dependen de la Prefectura
        // ─────────────────────────────────────────────────────────
        $nivel2 = [
            ['codigo' => 'GAD', 'nombre' => 'GESTIÓN ADMINISTRATIVA', 'tipo' => '22222222-2222-2222-2222-222222222222'],
            ['codigo' => 'SG', 'nombre' => 'GESTIÓN DE SECRETARÍA GENERAL', 'tipo' => '22222222-2222-2222-2222-222222222222'],
            ['codigo' => 'GAL', 'nombre' => 'GESTIÓN DE ASESORÍA LEGAL', 'tipo' => '33333333-3333-3333-3333-333333333333'],
            ['codigo' => 'GTH', 'nombre' => 'GESTIÓN DE TALENTO HUMANO', 'tipo' => '22222222-2222-2222-2222-222222222222'],
            ['codigo' => 'GF', 'nombre' => 'GESTIÓN FINANCIERA', 'tipo' => '22222222-2222-2222-2222-222222222222'],
            ['codigo' => 'GCS', 'nombre' => 'GESTIÓN DE COMUNICACION SOCIAL', 'tipo' => '33333333-3333-3333-3333-333333333333'],
            ['codigo' => 'GA', 'nombre' => 'GESTIÓN AMBIENTAL', 'tipo' => '44444444-4444-4444-4444-444444444444'],
            ['codigo' => 'GAI', 'nombre' => 'GESTIÓN DE AUDITORIA INTERNA', 'tipo' => '33333333-3333-3333-3333-333333333333'],
            ['codigo' => 'GFODEPRO', 'nombre' => 'GESTIÓN DE FOMENTO Y DESARROLLO PRODUCTIVO', 'tipo' => '44444444-4444-4444-4444-444444444444'],
            ['codigo' => 'GFZ', 'nombre' => 'GESTIÓN DE FISCALIZACIÓN', 'tipo' => '22222222-2222-2222-2222-222222222222'],
            ['codigo' => 'GIV', 'nombre' => 'GESTIÓN DE INFRAESTRUCTURA VIAL PROVINCIAL', 'tipo' => '44444444-4444-4444-4444-444444444444'],
            ['codigo' => 'GTIC', 'nombre' => 'GESTIÓN DE TECNOLOGIAS DE LA INFORMACIÓN Y COMUNICACIÓN', 'tipo' => '22222222-2222-2222-2222-222222222222'],
            ['codigo' => 'UCP', 'nombre' => 'UNIDAD DE COMPRAS PÚBLICAS', 'tipo' => '22222222-2222-2222-2222-222222222222'],
            ['codigo' => 'GCI', 'nombre' => 'GESTIÓN DE COORDINACIÓN INSTITUCIONAL', 'tipo' => '22222222-2222-2222-2222-222222222222'],
            ['codigo' => 'GC', 'nombre' => 'UNIDAD DE GESTIÓN DE CALIDAD', 'tipo' => '33333333-3333-3333-3333-333333333333'],
            ['codigo' => 'GCRD', 'nombre' => 'GESTIÓN DE CUENCAS, RIEGO Y DRENAJE', 'tipo' => '44444444-4444-4444-4444-444444444444'],
            ['codigo' => 'GACIT', 'nombre' => 'GESTIÓN DE RELACIONES INTERNACIONALES Y COOPERACIÓN', 'tipo' => '44444444-4444-4444-4444-444444444444'],
        ];

        $direccionesGuardadas = [];

        foreach ($nivel2 as $unidad) {
            $direccionesGuardadas[$unidad['codigo']] = UnidadAdministrativa::firstOrCreate(
                ['codigo' => $unidad['codigo']],
                [
                    'nombre' => $unidad['nombre'],
                    'tipo_unidad_id' => $unidad['tipo'],
                    'nivel' => 2,
                    'unidad_padre_id' => $prefectura->id,
                ]
            );
        }

        // ─────────────────────────────────────────────────────────
        // NIVEL 3: Subprocesos
        // Agregamos un par de subprocesos de ejemplo para ilustrar la profundidad.
        // ─────────────────────────────────────────────────────────
        
        // Subprocesos de Talento Humano (Habilitantes de Apoyo)
        UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'SUB-GTH-NOM'],
            [
                'nombre' => 'SUBPROCESO DE NÓMINA Y REMUNERACIONES',
                'tipo_unidad_id' => '22222222-2222-2222-2222-222222222222', 
                'nivel' => 3,
                'unidad_padre_id' => $direccionesGuardadas['GTH']->id,
            ]
        );

        UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'SUB-GTH-BIE'],
            [
                'nombre' => 'SUBPROCESO DE BIENESTAR SOCIAL Y SSO',
                'tipo_unidad_id' => '22222222-2222-2222-2222-222222222222', 
                'nivel' => 3,
                'unidad_padre_id' => $direccionesGuardadas['GTH']->id,
            ]
        );

        // Subprocesos de Tecnología (Habilitantes de Apoyo)
        UnidadAdministrativa::firstOrCreate(
            ['codigo' => 'SUB-GTIC-DEV'],
            [
                'nombre' => 'SUBPROCESO DE DESARROLLO DE SISTEMAS',
                'tipo_unidad_id' => '22222222-2222-2222-2222-222222222222', 
                'nivel' => 3,
                'unidad_padre_id' => $direccionesGuardadas['GTIC']->id,
            ]
        );
    }
}

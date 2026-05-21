<?php
namespace Database\Seeders;

use App\Models\Estructura\GrupoOcupacional;
use Illuminate\Database\Seeder;

class GrupoOcupacionalSeeder extends Seeder
{
    public function run(): void
    {
        $grupos = [
            // ── Dignatarios ──────────────────────────────
            [
                'grado_codigo'        => 'NJS-10',
                'grado_numerico'      => 10,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Prefecto/a',
                'rmu'                 => 5060.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'dignatario',
            ],
            [
                'grado_codigo'        => 'NJS-7',
                'grado_numerico'      => 7,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Viceprefecto/a',
                'rmu'                 => 4048.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'dignatario',
            ],
            // ── NJS Directivos ───────────────────────────
            [
                'grado_codigo'        => 'NJS-6',
                'grado_numerico'      => 6,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Coordinador/a, Procurador/a, Secretario/a General',
                'rmu'                 => 3848.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            [
                'grado_codigo'        => 'NJS-5-ASESOR',
                'grado_numerico'      => 5,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Asesor/a 2',
                'rmu'                 => 3247.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            [
                'grado_codigo'        => 'NJS-5',
                'grado_numerico'      => 5,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Director/a',
                'rmu'                 => 2967.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            [
                'grado_codigo'        => 'NJS-1',
                'grado_numerico'      => 1,
                'grupo'               => 'Nivel Jerárquico Superior',
                'denominacion_generica' => 'Coordinador/a, Tesorero/a',
                'rmu'                 => 2034.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            // ── Servidores Públicos de Carrera ───────────
            [
                'grado_codigo'        => 'SP9',
                'grado_numerico'      => 15,
                'grupo'               => 'Servidor Público 9',
                'denominacion_generica' => 'Especialista',
                'rmu'                 => 2034.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            [
                'grado_codigo'        => 'SP8',
                'grado_numerico'      => 14,
                'grupo'               => 'Servidor Público 8',
                'denominacion_generica' => 'Especialista',
                'rmu'                 => 1760.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_coordinacion',
            ],
            [
                'grado_codigo'        => 'SP7',
                'grado_numerico'      => 13,
                'grupo'               => 'Servidor Público 7',
                'denominacion_generica' => 'Analista',
                'rmu'                 => 1676.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP6',
                'grado_numerico'      => 12,
                'grupo'               => 'Servidor Público 6',
                'denominacion_generica' => 'Analista',
                'rmu'                 => 1412.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'alto',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP5',
                'grado_numerico'      => 11,
                'grupo'               => 'Servidor Público 5',
                'denominacion_generica' => 'Analista',
                'rmu'                 => 1212.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'medio',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP4',
                'grado_numerico'      => 10,
                'grupo'               => 'Servidor Público 4',
                'denominacion_generica' => 'Asistente',
                'rmu'                 => 1086.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'medio',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP3',
                'grado_numerico'      => 9,
                'grupo'               => 'Servidor Público 3',
                'denominacion_generica' => 'Asistente',
                'rmu'                 => 986.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'medio',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP2',
                'grado_numerico'      => 8,
                'grupo'               => 'Servidor Público 2',
                'denominacion_generica' => 'Asistente',
                'rmu'                 => 901.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'medio',
                'rol_puesto'          => 'ejecucion_procesos',
            ],
            [
                'grado_codigo'        => 'SP1',
                'grado_numerico'      => 7,
                'grupo'               => 'Servidor Público 1',
                'denominacion_generica' => 'Asistente de Apoyo',
                'rmu'                 => 817.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'ejecucion_procesos_apoyo',
            ],
            // ── Servidor Público de Apoyo ────────────────
            [
                'grado_codigo'        => 'SPA4',
                'grado_numerico'      => 6,
                'grupo'               => 'Servidor Público de Apoyo 4',
                'denominacion_generica' => 'Asistente Administrativo de Apoyo',
                'rmu'                 => 733.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            [
                'grado_codigo'        => 'SPA3',
                'grado_numerico'      => 5,
                'grupo'               => 'Servidor Público de Apoyo 3',
                'denominacion_generica' => 'Asistente Administrativo de Apoyo',
                'rmu'                 => 675.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            [
                'grado_codigo'        => 'SPA2',
                'grado_numerico'      => 4,
                'grupo'               => 'Servidor Público de Apoyo 2',
                'denominacion_generica' => 'Asistente Administrativo de Apoyo',
                'rmu'                 => 622.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            [
                'grado_codigo'        => 'SPA1',
                'grado_numerico'      => 3,
                'grupo'               => 'Servidor Público de Apoyo 1',
                'denominacion_generica' => 'Asistente Administrativo de Apoyo',
                'rmu'                 => 585.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            // ── Servidor Público de Servicios ────────────
            [
                'grado_codigo'        => 'SPS2',
                'grado_numerico'      => 2,
                'grupo'               => 'Servidor Público de Servicios 2',
                'denominacion_generica' => 'Asistente Administrativo de Servicios',
                'rmu'                 => 553.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            [
                'grado_codigo'        => 'SPS1',
                'grado_numerico'      => 1,
                'grupo'               => 'Servidor Público de Servicios 1',
                'denominacion_generica' => 'Asistente Administrativo de Servicios',
                'rmu'                 => 527.00,
                'regimen'             => 'losep',
                'nivel_complejidad'   => 'bajo',
                'rol_puesto'          => 'administrativo',
            ],
            // ── Código del Trabajo ───────────────────────
            // Sin escala fija. La RMU real va en contratos_servidor.
            // Este registro es solo referencial — el piso es el SBU.
            [
                'grado_codigo'        => 'CT',
                'grado_numerico'      => null,
                'grupo'               => 'Trabajador Código del Trabajo',
                'denominacion_generica' => 'Trabajador/a',
                'rmu'                 => 460.00,
                'regimen'             => 'codigo_trabajo',
                'nivel_complejidad'   => null,
                'rol_puesto'          => 'codigo_trabajo',
            ],
        ];

        foreach ($grupos as $grupo) {
            GrupoOcupacional::firstOrCreate(
                ['grado_codigo' => $grupo['grado_codigo']],
                $grupo
            );
        }
    }
}

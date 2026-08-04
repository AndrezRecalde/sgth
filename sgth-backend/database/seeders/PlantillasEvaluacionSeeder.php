<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Seleccion\PlantillaEvaluacion;
use App\Models\Seleccion\PlantillaCriterio;
use App\Models\Seleccion\PlantillaOpcion;

class PlantillasEvaluacionSeeder extends Seeder
{
    public function run(): void
    {
        $plantillas = [
            [
                'nombre'        => 'Concurso de Méritos y Oposición — LOSEP',
                'descripcion'   => 'Plantilla estándar para concursos de méritos y oposición bajo la Ley Orgánica del Servicio Público (LOSEP). Méritos: 40%, Oposición: 60%.',
                'tipo_contrato' => 'losep',
                'criterios' => [
                    [
                        'seccion'        => 'meritos',
                        'nombre'         => 'Instrucción formal',
                        'descripcion'    => 'Nivel de instrucción académica del candidato relacionada con el puesto convocado.',
                        'puntaje_maximo' => 10,
                        'tipo_input'     => 'radio',
                        'orden'          => 1,
                        'opciones' => [
                            ['etiqueta' => 'Doctorado (PhD)', 'puntaje' => 10, 'orden' => 1],
                            ['etiqueta' => 'Maestría',        'puntaje' => 8,  'orden' => 2],
                            ['etiqueta' => 'Especialización', 'puntaje' => 6,  'orden' => 3],
                            ['etiqueta' => 'Tercer nivel (pregrado)', 'puntaje' => 4, 'orden' => 4],
                            ['etiqueta' => 'Tecnólogo / Técnico', 'puntaje' => 2, 'orden' => 5],
                        ],
                    ],
                    [
                        'seccion'        => 'meritos',
                        'nombre'         => 'Experiencia laboral',
                        'descripcion'    => 'Años de experiencia en cargos relacionados al puesto convocado. Ingrese el puntaje calculado según la tabla del MRL.',
                        'puntaje_maximo' => 20,
                        'tipo_input'     => 'numero',
                        'orden'          => 2,
                        'opciones'       => [],
                    ],
                    [
                        'seccion'        => 'meritos',
                        'nombre'         => 'Capacitación y formación continua',
                        'descripcion'    => 'Cursos, seminarios y talleres relacionados al puesto de los últimos 5 años. Se acumulan hasta el máximo de 10 puntos.',
                        'puntaje_maximo' => 10,
                        'tipo_input'     => 'checklist',
                        'orden'          => 3,
                        'opciones' => [
                            ['etiqueta' => 'Curso de 40 o más horas',    'puntaje' => 2, 'orden' => 1],
                            ['etiqueta' => 'Curso de 20 a 39 horas',     'puntaje' => 1, 'orden' => 2],
                            ['etiqueta' => 'Curso de 10 a 19 horas',     'puntaje' => 0.5, 'orden' => 3],
                        ],
                    ],
                    [
                        'seccion'        => 'oposicion',
                        'nombre'         => 'Prueba de conocimientos técnicos',
                        'descripcion'    => 'Evaluación escrita sobre conocimientos técnicos y legales relacionados al puesto. Puntaje mínimo para continuar: 21/30.',
                        'puntaje_maximo' => 30,
                        'tipo_input'     => 'numero',
                        'orden'          => 1,
                        'opciones'       => [],
                    ],
                    [
                        'seccion'        => 'oposicion',
                        'nombre'         => 'Prueba psicométrica',
                        'descripcion'    => 'Test de aptitudes, personalidad y compatibilidad con el perfil del cargo.',
                        'puntaje_maximo' => 10,
                        'tipo_input'     => 'numero',
                        'orden'          => 2,
                        'opciones'       => [],
                    ],
                    [
                        'seccion'        => 'oposicion',
                        'nombre'         => 'Entrevista técnica',
                        'descripcion'    => 'Entrevista estructurada con el panel evaluador. Se evalúan competencias técnicas y conductuales.',
                        'puntaje_maximo' => 20,
                        'tipo_input'     => 'numero',
                        'orden'          => 3,
                        'opciones'       => [],
                    ],
                ],
            ],
            [
                'nombre'        => 'Contrato — Código de Trabajo',
                'descripcion'   => 'Plantilla para procesos de selección bajo el Código de Trabajo. Aplicable para obreros y personal operativo.',
                'tipo_contrato' => 'codigo_trabajo',
                'criterios' => [
                    [
                        'seccion'        => 'meritos',
                        'nombre'         => 'Instrucción formal',
                        'descripcion'    => 'Nivel de instrucción académica del candidato.',
                        'puntaje_maximo' => 10,
                        'tipo_input'     => 'radio',
                        'orden'          => 1,
                        'opciones' => [
                            ['etiqueta' => 'Tercer nivel o superior', 'puntaje' => 10, 'orden' => 1],
                            ['etiqueta' => 'Tecnólogo / Técnico',     'puntaje' => 7,  'orden' => 2],
                            ['etiqueta' => 'Bachiller',               'puntaje' => 4,  'orden' => 3],
                            ['etiqueta' => 'Educación básica',        'puntaje' => 1,  'orden' => 4],
                        ],
                    ],
                    [
                        'seccion'        => 'meritos',
                        'nombre'         => 'Experiencia laboral',
                        'descripcion'    => 'Años de experiencia en el área relacionada.',
                        'puntaje_maximo' => 30,
                        'tipo_input'     => 'numero',
                        'orden'          => 2,
                        'opciones'       => [],
                    ],
                    [
                        'seccion'        => 'oposicion',
                        'nombre'         => 'Prueba práctica',
                        'descripcion'    => 'Demostración práctica de habilidades y destrezas requeridas para el puesto.',
                        'puntaje_maximo' => 40,
                        'tipo_input'     => 'numero',
                        'orden'          => 1,
                        'opciones'       => [],
                    ],
                    [
                        'seccion'        => 'oposicion',
                        'nombre'         => 'Entrevista',
                        'descripcion'    => 'Entrevista con el panel evaluador.',
                        'puntaje_maximo' => 20,
                        'tipo_input'     => 'numero',
                        'orden'          => 2,
                        'opciones'       => [],
                    ],
                ],
            ],
            [
                'nombre'        => 'Servicios Profesionales / Ocasional',
                'descripcion'   => 'Plantilla para contratación de servicios profesionales y contratos ocasionales. Proceso simplificado.',
                'tipo_contrato' => 'servicios_profesionales',
                'criterios' => [
                    [
                        'seccion'        => 'meritos',
                        'nombre'         => 'Instrucción formal',
                        'descripcion'    => 'Nivel de instrucción requerida para la prestación del servicio.',
                        'puntaje_maximo' => 15,
                        'tipo_input'     => 'radio',
                        'orden'          => 1,
                        'opciones' => [
                            ['etiqueta' => 'Doctorado / Maestría',   'puntaje' => 15, 'orden' => 1],
                            ['etiqueta' => 'Tercer nivel',           'puntaje' => 10, 'orden' => 2],
                            ['etiqueta' => 'Tecnólogo / Técnico',    'puntaje' => 6,  'orden' => 3],
                        ],
                    ],
                    [
                        'seccion'        => 'meritos',
                        'nombre'         => 'Experiencia en el área',
                        'descripcion'    => 'Años de experiencia comprobada en servicios similares.',
                        'puntaje_maximo' => 25,
                        'tipo_input'     => 'numero',
                        'orden'          => 2,
                        'opciones'       => [],
                    ],
                    [
                        'seccion'        => 'oposicion',
                        'nombre'         => 'Propuesta técnica / portafolio',
                        'descripcion'    => 'Evaluación de la propuesta técnica o portafolio de trabajos presentado.',
                        'puntaje_maximo' => 35,
                        'tipo_input'     => 'numero',
                        'orden'          => 1,
                        'opciones'       => [],
                    ],
                    [
                        'seccion'        => 'oposicion',
                        'nombre'         => 'Entrevista técnica',
                        'descripcion'    => 'Entrevista para verificar competencias y experiencia del profesional.',
                        'puntaje_maximo' => 25,
                        'tipo_input'     => 'numero',
                        'orden'          => 2,
                        'opciones'       => [],
                    ],
                ],
            ],
        ];

        // Idempotente: se identifica cada nivel por su clave natural (nombre
        // de la plantilla, sección+nombre del criterio, etiqueta de la
        // opción). Con create() a secas, cada corrida duplicaba las tres
        // plantillas junto con todos sus criterios y opciones, y las
        // duplicadas aparecían como opciones distintas al aplicar una
        // plantilla a una convocatoria.
        foreach ($plantillas as $data) {
            $criteriosData = $data['criterios'];
            unset($data['criterios']);

            $plantilla = PlantillaEvaluacion::updateOrCreate(
                ['nombre' => $data['nombre']],
                $data
            );

            foreach ($criteriosData as $criterioData) {
                $opcionesData = $criterioData['opciones'];
                unset($criterioData['opciones']);

                $criterio = PlantillaCriterio::updateOrCreate(
                    [
                        'plantilla_id' => $plantilla->id,
                        'seccion'      => $criterioData['seccion'],
                        'nombre'       => $criterioData['nombre'],
                    ],
                    $criterioData
                );

                foreach ($opcionesData as $opcion) {
                    PlantillaOpcion::updateOrCreate(
                        [
                            'plantilla_criterio_id' => $criterio->id,
                            'etiqueta'              => $opcion['etiqueta'],
                        ],
                        $opcion
                    );
                }
            }
        }
    }
}

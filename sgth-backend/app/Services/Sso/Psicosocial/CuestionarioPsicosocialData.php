<?php

namespace App\Services\Sso\Psicosocial;

/**
 * Cuestionario de evaluación de riesgo psicosocial — Ministerio del Trabajo del Ecuador,
 * "Guía para la aplicación del cuestionario de evaluación de riesgo psicosocial" (octubre 2018).
 * 58 ítems verbatim, 8 dimensiones (Tabla 2) y tablas de rango de puntaje (Tabla 3 y Tabla 4).
 * En este instrumento el puntaje se interpreta de forma inversa: a MENOR puntaje, MAYOR riesgo.
 */
final class CuestionarioPsicosocialData
{
    /** @return array<int, array{dimension: string, subdimension: ?string, texto: string}> */
    public static function preguntas(): array
    {
        $textos = [
            1 => 'Considero que son aceptables las solicitudes y requerimientos que me piden otras personas (compañeros de trabajo, usuarios, clientes)',
            2 => 'Decido el ritmo de trabajo en mis actividades',
            3 => 'Las actividades y/o responsabilidades que me fueron asignadas no me causan estrés',
            4 => 'Tengo suficiente tiempo para realizar todas las actividades que me han sido encomendadas dentro de mi jornada laboral',
            5 => 'Considero que tengo los suficientes conocimientos, habilidades y destrezas para desarrollar el trabajo para el cual fuí contratado',
            6 => 'En mi trabajo aprendo y adquiero nuevos conocimientos, habilidades y destrezas de mis compañeros de trabajo',
            7 => 'En mi trabajo se cuenta con un plan de carrera, capacitación y/o entrenamiento para el desarrollo de mis conocimientos, habilidades y destrezas',
            8 => 'En mi trabajo se evalúa objetiva y periódicamente las actividades que realizo',
            9 => 'En mi trabajo se reconoce y se da crédito a la persona que realiza un buen trabajo o logran sus objetivos.',
            10 => 'Mi jefe inmediato está dispuesto a escuchar propuestas de cambio e iniciativas de trabajo',
            11 => 'Mi jefe inmediato establece metas, plazos claros y factibles para el cumplimiento de mis funciones o actividades',
            12 => 'Mi jefe inmediato interviene, brinda apoyo, soporte y se preocupa cuando tengo demasiado trabajo que realizar',
            13 => 'Mi jefe inmediato me brinda suficientes lineamientos y retroalimentación para el desempeño de mi trabajo',
            14 => 'Mi jefe inmediato pone en consideración del equipo de trabajo, las decisiones que pueden afectar a todos.',
            15 => 'En mi trabajo existen espacios de discusión para debatir abiertamente los problemas comunes y diferencias de opinión',
            16 => 'Me es permitido realizar el trabajo con colaboración de mis compañeros de trabajo y/u otras áreas',
            17 => 'Mi opinión es tomada en cuenta con respecto a fechas límites en el cumplimiento de mis actividades o cuando exista cambio en mis funciones',
            18 => 'Se me permite aportar con ideas para mejorar las actividades y la organización del trabajo',
            19 => 'Considero que las formas de comunicación en mi trabajo son adecuados, accesibles y de fácil comprensión',
            20 => 'En mi trabajo se informa regularmente de la gestión y logros de la empresa o institución a todos los trabajadores y servidores',
            21 => 'En mi trabajo se respeta y se toma en consideración las limitaciones de las personas con discapacidad para la asignación de roles y tareas',
            22 => 'En mi trabajo tenemos reuniones suficientes y significantes para el cumplimiento de los objetivos',
            23 => 'Las metas y objetivos en mi trabajo son claros y alcanzables',
            24 => 'Siempre dispongo de tareas y actividades a realizar en mi jornada y lugar de trabajo',
            25 => 'Después del trabajo tengo la suficiente energía como para realizar otras actividades',
            26 => 'En mi trabajo se me permite realizar pausas de periodo corto para renovar y recuperar la energía.',
            27 => 'En mi trabajo tengo tiempo para dedicarme a reflexionar sobre mi desempeño en el trabajo',
            28 => 'Tengo un horario y jornada de trabajo que se ajusta a mis expectativas y exigencias laborales',
            29 => 'Todos los días siento que he descansado lo suficiente y que tengo la energía para iniciar mi trabajo',
            30 => 'El trabajo está organizado de tal manera que fomenta la colaboración de equipo y el diálogo con otras personas',
            31 => 'En mi trabajo percibo un sentimiento de compañerismo y bienestar con mis colegas',
            32 => 'En mi trabajo se brinda el apoyo necesario a los trabajadores sustitutos o trabajadores con algún grado de discapacidad y enfermedad',
            33 => 'En mi trabajo se me brinda ayuda técnica y administrativa cuando lo requiero',
            34 => 'En mi trabajo tengo acceso a la atención de un médico, psicólogo, trabajadora social, consejero, etc. en situaciones de crisis y/o rehabilitación',
            35 => 'En mi trabajo tratan por igual a todos, indistintamente la edad que tengan',
            36 => 'Las directrices y metas que me autoimpongo, las cumplo dentro de mi jornada y horario de trabajo',
            37 => 'En mi trabajo existe un buen ambiente laboral',
            38 => 'Tengo un trabajo donde los hombres y mujeres tienen las mismas oportunidades',
            39 => 'En mi trabajo me siento aceptado y valorado',
            40 => 'Los espacios y ambientes físicos en mi trabajo brindan las facilidades para el acceso de las personas con discapacidad',
            41 => 'Considero que mi trabajo está libre de amenazas, humillaciones, ridiculizaciones, burlas, calumnias o difamaciones reiteradas con el fin de causarme daño',
            42 => 'Me siento estable a pesar de cambios que se presentan en mi trabajo',
            43 => 'En mi trabajo estoy libre de conductas sexuales que afecten mi integridad física, psicológica y moral',
            44 => 'Considero que el trabajo que realizo no me causa efectos negativos a mi salud física y mental',
            45 => 'Me resulta fácil relajarme cuando no estoy trabajando',
            46 => 'Siento que mis problemas familiares o personales no influyen en el desempeño de las actividades en el trabajo',
            47 => 'Las instalaciones, ambientes, equipos, maquinaria y herramientas que utilizo para realizar el trabajo son las adecuadas para no sufrir accidentes de trabajo y enfermedades profesionales',
            48 => 'Mi trabajo esta libre de acoso sexual',
            49 => 'En mi trabajo se me permite solucionar mis problemas familiares y personales',
            50 => 'Tengo un trabajo libre de conflictos estresantes, rumores maliciosos o calumniosos sobre mi persona',
            51 => 'Tengo un equilibrio y separo bien el trabajo de mi vida personal',
            52 => 'Estoy orgulloso de trabajar en mi empresa o institución',
            53 => 'En mi trabajo se respeta mi ideología, opinión política, religiosa, nacionalidad y orientación sexual.',
            54 => 'Mi trabajo y los aportes que realizo son valorados y me generan motivación',
            55 => 'Me siento libre de culpa cuando no estoy trabajando en algo',
            56 => 'En mi trabajo no existen espacios de uso exclusivo de un grupo determinado de personas ligados a un privilegio, por ejemplo, cafetería exclusiva, baños exclusivos, etc., mismo que causa malestar y perjudica mi ambiente laboral',
            57 => 'Puedo dejar de pensar en el trabajo durante mi tiempo libre (pasatiempos, actividades de recreación, otros)',
            58 => 'Considero que me encuentro física y mentalmente saludable',
        ];

        $preguntas = [];
        foreach (self::dimensiones() as $dimensionKey => $dimension) {
            foreach ($dimension['items'] as $numero) {
                $preguntas[$numero] = [
                    'dimension' => $dimensionKey,
                    'subdimension' => null,
                    'texto' => $textos[$numero],
                ];
            }
        }
        foreach (self::subdimensiones() as $subdimensionKey => $subdimension) {
            foreach ($subdimension['items'] as $numero) {
                $preguntas[$numero]['subdimension'] = $subdimensionKey;
            }
        }

        ksort($preguntas);
        return $preguntas;
    }

    /**
     * Tabla 2 + Tabla 3: las 8 dimensiones principales, sus ítems y su rango de puntaje
     * (riesgo bajo/medio/alto). "otros_puntos_importantes" agrupa los 24 ítems 35-58.
     *
     * @return array<string, array{etiqueta: string, items: int[], rangos: array}>
     */
    public static function dimensiones(): array
    {
        return [
            'carga_ritmo_trabajo' => [
                'etiqueta' => 'Carga y ritmo de trabajo',
                'items' => [1, 2, 3, 4],
                'rangos' => ['alto' => [4, 7], 'medio' => [8, 12], 'bajo' => [13, 16]],
            ],
            'desarrollo_competencias' => [
                'etiqueta' => 'Desarrollo de competencias',
                'items' => [5, 6, 7, 8],
                'rangos' => ['alto' => [4, 7], 'medio' => [8, 12], 'bajo' => [13, 16]],
            ],
            'liderazgo' => [
                'etiqueta' => 'Liderazgo',
                'items' => [9, 10, 11, 12, 13, 14],
                'rangos' => ['alto' => [6, 11], 'medio' => [12, 17], 'bajo' => [18, 24]],
            ],
            'margen_accion_control' => [
                'etiqueta' => 'Margen de acción y control',
                'items' => [15, 16, 17, 18],
                'rangos' => ['alto' => [4, 7], 'medio' => [8, 12], 'bajo' => [13, 16]],
            ],
            'organizacion_trabajo' => [
                'etiqueta' => 'Organización del trabajo',
                'items' => [19, 20, 21, 22, 23, 24],
                'rangos' => ['alto' => [6, 11], 'medio' => [12, 17], 'bajo' => [18, 24]],
            ],
            'recuperacion' => [
                'etiqueta' => 'Recuperación',
                'items' => [25, 26, 27, 28, 29],
                'rangos' => ['alto' => [5, 9], 'medio' => [10, 15], 'bajo' => [16, 20]],
            ],
            'soporte_apoyo' => [
                'etiqueta' => 'Soporte y apoyo',
                'items' => [30, 31, 32, 33, 34],
                'rangos' => ['alto' => [5, 9], 'medio' => [10, 15], 'bajo' => [16, 20]],
            ],
            'otros_puntos_importantes' => [
                'etiqueta' => 'Otros puntos importantes',
                'items' => range(35, 58),
                'rangos' => ['alto' => [24, 48], 'medio' => [49, 72], 'bajo' => [73, 96]],
            ],
        ];
    }

    /**
     * Subdivisión informativa de "Otros puntos importantes" (Tabla 2 / Tabla 3).
     * No se suman aparte al puntaje global: sus ítems ya están contados en
     * "otros_puntos_importantes"; esto es solo un desglose de lectura.
     *
     * @return array<string, array{etiqueta: string, items: int[], rangos: array}>
     */
    public static function subdimensiones(): array
    {
        return [
            'acoso_discriminatorio' => [
                'etiqueta' => 'Acoso discriminatorio',
                'items' => [35, 38, 53, 56],
                'rangos' => ['alto' => [4, 7], 'medio' => [8, 12], 'bajo' => [13, 16]],
            ],
            'acoso_laboral' => [
                'etiqueta' => 'Acoso laboral',
                'items' => [41, 50],
                'rangos' => ['alto' => [2, 4], 'medio' => [5, 6], 'bajo' => [7, 8]],
            ],
            'acoso_sexual' => [
                'etiqueta' => 'Acoso sexual',
                'items' => [43, 48],
                'rangos' => ['alto' => [2, 4], 'medio' => [5, 6], 'bajo' => [7, 8]],
            ],
            'adiccion_trabajo' => [
                'etiqueta' => 'Adicción al trabajo',
                'items' => [36, 45, 51, 55, 57],
                'rangos' => ['alto' => [5, 9], 'medio' => [10, 15], 'bajo' => [16, 20]],
            ],
            'condiciones_trabajo' => [
                'etiqueta' => 'Condiciones del trabajo',
                'items' => [40, 47],
                'rangos' => ['alto' => [2, 4], 'medio' => [5, 6], 'bajo' => [7, 8]],
            ],
            'doble_presencia' => [
                'etiqueta' => 'Doble presencia (laboral-familiar)',
                'items' => [46, 49],
                'rangos' => ['alto' => [2, 4], 'medio' => [5, 6], 'bajo' => [7, 8]],
            ],
            'estabilidad_laboral_emocional' => [
                'etiqueta' => 'Estabilidad laboral y emocional',
                'items' => [37, 39, 42, 52, 54],
                'rangos' => ['alto' => [5, 9], 'medio' => [10, 15], 'bajo' => [16, 20]],
            ],
            'salud_auto_percibida' => [
                'etiqueta' => 'Salud auto percibida',
                'items' => [44, 58],
                'rangos' => ['alto' => [2, 4], 'medio' => [5, 6], 'bajo' => [7, 8]],
            ],
        ];
    }

    /** Tabla 4: nivel de riesgo general (suma de las 8 dimensiones, ítems 1 a 58). */
    public static function rangoGlobal(): array
    {
        return ['alto' => [58, 116], 'medio' => [117, 174], 'bajo' => [175, 232]];
    }

    /**
     * Opciones verbatim de la sección "Datos generales" (ítems D-I) del cuestionario oficial:
     * sociodemográficas, sin identificar a la persona.
     *
     * @return array<string, array<string, string>>
     */
    public static function datosGeneralesOpciones(): array
    {
        return [
            'area_trabajo' => [
                'administrativa' => 'Administrativa',
                'operativa' => 'Operativa',
            ],
            'nivel_instruccion' => [
                'ninguno' => 'Ninguno',
                'educacion_basica' => 'Educación básica',
                'educacion_media' => 'Educación media',
                'bachillerato' => 'Bachillerato',
                'tecnico_tecnologico' => 'Técnico / Tecnológico',
                'tercer_nivel' => 'Tercer nivel',
                'cuarto_nivel' => 'Cuarto nivel',
                'otro' => 'Otro',
            ],
            'antiguedad' => [
                '0-2' => '0-2 años',
                '3-10' => '3-10 años',
                '11-20' => '11-20 años',
                '21+' => 'Igual o superior a 21 años',
            ],
            'rango_edad' => [
                '16-24' => '16-24 años',
                '25-34' => '25-34 años',
                '35-43' => '35-43 años',
                '44-52' => '44-52 años',
                '53+' => 'Igual o superior a 53 años',
            ],
            'autoidentificacion_etnica' => [
                'indigena' => 'Indígena',
                'mestizo' => 'Mestizo/a',
                'montubio' => 'Montubio/a',
                'afroecuatoriano' => 'Afro-ecuatoriano',
                'blanco' => 'Blanco/a',
                'otro' => 'Otro',
            ],
            'genero' => [
                'masculino' => 'Masculino',
                'femenino' => 'Femenino',
            ],
        ];
    }
}

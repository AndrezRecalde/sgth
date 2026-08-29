<?php

namespace App\Catalogos;

/**
 * Sección G del formulario SNS-MSP/HCU-form.123/2025 — factores de riesgo del
 * trabajo actual.
 *
 * Es el catálogo OFICIAL, con la redacción literal del impreso. No se edita
 * para «mejorar» un nombre: la ficha que emite el dispensario debe poder
 * cotejarse contra el formulario del MSP casilla por casilla, y los indicadores
 * de seguridad y salud ocupacional se agregan por esta taxonomía.
 *
 * Vive en el backend y no en el formulario porque lo consumen tres cosas: la
 * validación al guardar, la pantalla de captura y la generación del PDF.
 * Tenerlo escrito en el frontend hacía que las tres pudieran discrepar — y
 * discrepaban: el catálogo anterior tenía 32 factores inventados
 * («Trabajo en alturas», «Espacios confinados», «Presión anormal»,
 * «Trabajo nocturno») y fundía en «Temperatura extrema» las dos filas que el
 * MSP separa en altas y bajas.
 *
 * Las categorías coinciden con `App\Enums\CategoriaRiesgoLaboral`. Solo «De
 * seguridad» tiene subcategorías; en las demás los factores cuelgan directo,
 * y ahí `subcategoria` viaja como `null`.
 */
final class FactoresRiesgoMsp
{
    /**
     * @return array<string, array{etiqueta: string, grupos: array<int, array{
     *     subcategoria: string|null, etiqueta: string|null, factores: array<int, string>
     * }>}>
     */
    public static function catalogo(): array
    {
        return [
            'fisico' => [
                'etiqueta' => 'Físico',
                'grupos' => [[
                    'subcategoria' => null,
                    'etiqueta' => null,
                    'factores' => [
                        'Temperaturas altas',
                        'Temperaturas bajas',
                        'Radiación ionizante',
                        'Radiación no ionizante',
                        'Ruido',
                        'Vibración',
                        'Iluminación',
                        'Ventilación',
                        'Fluido eléctrico',
                        'Otros',
                    ],
                ]],
            ],

            'seguridad' => [
                'etiqueta' => 'De seguridad',
                'grupos' => [
                    [
                        'subcategoria' => 'locativos',
                        'etiqueta' => 'Locativos',
                        'factores' => [
                            'Falta de señalización, aseo, desorden',
                        ],
                    ],
                    [
                        'subcategoria' => 'mecanicos',
                        'etiqueta' => 'Mecánicos',
                        'factores' => [
                            'Atrapamiento entre máquinas y/o superficies',
                            'Atrapamiento entre objetos',
                            'Caída de objetos',
                            'Caídas al mismo nivel',
                            'Caídas a diferente nivel',
                            'Pinchazos',
                            'Cortes',
                            'Choques / colisión vehicular',
                            'Atropellamientos por vehículos',
                            'Proyección de fluidos',
                            'Proyección de partículas – fragmentos',
                            'Contacto con superficies de trabajo',
                        ],
                    ],
                    [
                        'subcategoria' => 'electricos',
                        'etiqueta' => 'Eléctricos',
                        'factores' => [
                            'Contacto eléctrico',
                        ],
                    ],
                    [
                        'subcategoria' => 'otros',
                        'etiqueta' => 'Otros',
                        'factores' => [
                            'Otros',
                        ],
                    ],
                ],
            ],

            'quimico' => [
                'etiqueta' => 'Químico',
                'grupos' => [[
                    'subcategoria' => null,
                    'etiqueta' => null,
                    'factores' => [
                        'Polvos',
                        'Sólidos',
                        'Humos',
                        'Líquidos',
                        'Vapores',
                        'Aerosoles',
                        'Neblinas',
                        'Gaseosos',
                        'Otros',
                    ],
                ]],
            ],

            'biologico' => [
                'etiqueta' => 'Biológico',
                'grupos' => [[
                    'subcategoria' => null,
                    'etiqueta' => null,
                    'factores' => [
                        'Virus',
                        'Hongos',
                        'Bacterias',
                        'Parásitos',
                        'Exposición a vectores',
                        'Exposición a animales selváticos',
                        'Otros',
                    ],
                ]],
            ],

            'ergonomico' => [
                'etiqueta' => 'Ergonómico',
                'grupos' => [[
                    'subcategoria' => null,
                    'etiqueta' => null,
                    'factores' => [
                        'Manejo manual de cargas',
                        'Movimientos repetitivos',
                        'Posturas forzadas',
                        'Trabajos con PVD',
                        'Diseño inadecuado del puesto',
                        'Otros',
                    ],
                ]],
            ],

            'psicosocial' => [
                'etiqueta' => 'Psicosocial',
                'grupos' => [[
                    'subcategoria' => null,
                    'etiqueta' => null,
                    'factores' => [
                        'Monotonía del trabajo',
                        'Sobrecarga laboral',
                        'Minuciosidad de la tarea',
                        'Alta responsabilidad',
                        'Autonomía en la toma de decisiones',
                        'Supervisión y estilos de dirección deficiente',
                        'Conflicto de rol',
                        'Falta de claridad en las funciones',
                        'Incorrecta distribución del trabajo',
                        'Turnos rotativos',
                        'Relaciones interpersonales',
                        'Inestabilidad laboral',
                        'Amenaza delincuencial',
                        'Otros',
                    ],
                ]],
            ],
        ];
    }

    /** Nombres de factor válidos para una categoría. @return array<int, string> */
    public static function factoresDe(string $categoria): array
    {
        $grupos = self::catalogo()[$categoria]['grupos'] ?? [];

        return array_merge(...array_map(
            static fn (array $g) => $g['factores'],
            $grupos
        ));
    }

    /** Todos los nombres de factor, sin repetir. @return array<int, string> */
    public static function todosLosFactores(): array
    {
        $factores = [];
        foreach (array_keys(self::catalogo()) as $categoria) {
            $factores = [...$factores, ...self::factoresDe($categoria)];
        }

        return array_values(array_unique($factores));
    }

    /**
     * Subcategoría a la que pertenece un factor, o `null` si su categoría no
     * las usa. Sirve para completar el dato sin que el cliente lo mande.
     */
    public static function subcategoriaDe(string $categoria, string $factor): ?string
    {
        foreach (self::catalogo()[$categoria]['grupos'] ?? [] as $grupo) {
            if (in_array($factor, $grupo['factores'], true)) {
                return $grupo['subcategoria'];
            }
        }

        return null;
    }

    /** Cuántos factores define el formulario. Referencia: 61. */
    public static function total(): int
    {
        $n = 0;
        foreach (array_keys(self::catalogo()) as $categoria) {
            $n += count(self::factoresDe($categoria));
        }

        return $n;
    }
}

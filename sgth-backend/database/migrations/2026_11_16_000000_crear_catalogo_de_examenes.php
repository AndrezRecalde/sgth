<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de exámenes que el médico puede pedir.
 *
 * Hasta ahora el Dispensario no tenía forma de pedir un examen: el médico lo
 * escribía en el plan de tratamiento y el paciente se llevaba la hoja de un
 * laboratorio privado. El catálogo de partida es esa hoja (decisión D7 del
 * Dispensario, 23-10-2026; anexo A de `docs/diseno-examenes-medicos.md`), con
 * las mismas áreas y en el mismo orden, y dos radiografías dentales para el
 * odontólogo (D12).
 *
 * Es un catálogo cerrado (D4): solo se pide lo que está aquí. Un examen que
 * falte lo agrega la administración del Dispensario, y uno retirado se marca
 * inactivo en vez de borrarse, porque las órdenes lo seguirán nombrando.
 *
 * Los datos van en la migración y no en un seeder para que el despliegue los
 * cargue con el mismo `php artisan migrate` que crea la tabla.
 */
return new class extends Migration
{
    /**
     * Por área, en el orden de la hoja. Un examen es su nombre, o su nombre con:
     * - `nota`: lo que el médico completa al pedirlo (los gramos de una
     *   tolerancia a la glucosa, el número de muestras de un seriado);
     * - `sensible`: pide la constancia de consentimiento firmado y no entra en
     *   perfiles ocupacionales (D8, P3).
     */
    private const CATALOGO = [
        'bioquimica' => [
            'Glucosa', 'Glucosa post-prandial',
            ['Tolerancia oral a la glucosa', 'nota' => 'Gramos de glucosa'],
            'Glicohemoglobina (HbA1c)', 'Fructosamina', 'Urea', 'BUN', 'Creatinina',
            'Ácido úrico', 'Colesterol', 'HDL colesterol', 'LDL colesterol',
            'VLDL colesterol', 'Triglicéridos', 'Lípidos totales', 'Fosfolípidos',
            'Lipoproteína A', 'Lipoproteína B', 'Proteínas totales', 'Albúminas',
            'Globulinas', 'Bilirrubina total, directa e indirecta', 'Cistatina C',
        ],
        'infecciosas' => [
            'HAV cualitativo', 'HAV IgG', 'HAV IgM', 'HBsAg cualitativo', 'HBsAg IgG',
            'HCV cualitativo', 'HCV IgG', 'HCV IgM', 'H. pylori IgG', 'H. pylori IgM',
            'H. pylori IgA', 'H. pylori cualitativo', 'Toxoplasma IgG',
            ['HIV 1/2/0', 'sensible' => true],
            'Toxoplasma cualitativo IgM', 'CMV IgG', 'CMV IgM', 'Herpes virus 1 IgG',
            'Herpes virus 1 IgM', 'Herpes virus 2 IgG', 'Herpes virus 2 IgM',
            'Dengue IgG-IgM', 'Leptospira IgG', 'Leptospira IgM', 'Rubéola IgG',
            'Rubéola IgM', 'S-TORCH IgG', 'S-TORCH IgM',
        ],
        'microbiologia' => [
            'Papanicolaou', 'Cultivo de exudado faríngeo', 'Urocultivo', 'Coprocultivo',
            'Cultivo de esputo', 'Cultivo de secreción vaginal',
            'Cultivo de líquido cefalorraquídeo', 'Cultivo de líquido espermático',
            'Hemocultivo', ['Cultivo de otra muestra', 'nota' => 'Tipo de muestra'],
        ],
        'inmunologia' => [
            'ANA Hep-2 IF', 'Anti-DNA ds IF', 'Anticentrómero IF', 'Células LE',
            'Complemento C3', 'Complemento C4', 'Inmunoglobulina G', 'Inmunoglobulina A',
            'Inmunoglobulina M', 'Inmunoglobulina E', 'CD4-CD8', 'Cadenas ligeras Kappa',
            'Cadenas ligeras Lambda', 'Anti-músculo liso', 'Anti-músculo estriado',
            'Anti-células parietales', 'Anticuerpos antimitocondriales',
        ],
        'drogas' => [
            'Acetaminofén', 'Ácido valproico', 'Benzodiazepinas', 'Carbamazepina',
            'Difenilhidantoína', 'Teofilina',
            ['Anfetaminas', 'sensible' => true], ['Éxtasis', 'sensible' => true],
            ['Cocaína', 'sensible' => true], ['Opio', 'sensible' => true],
            ['Tetrahidrocannabinol', 'sensible' => true],
        ],
        'hemostasia' => [
            'Recuento plaquetario', 'Tiempo de protrombina', 'Tiempo de tromboplastina',
            'Tiempo de coagulación', 'Tiempo de sangría', 'Fibrinógeno', 'Factor V',
            'Factor VIII', 'Factor IX', 'Factor XIII', 'Factor X',
            'Factor von Willebrand', 'Dímero D', 'Anticoagulante lúpico',
        ],
        'enzimas' => [
            'AST/TGO', 'ALT/TGP', 'Fosfatasa alcalina', 'Fosfatasa ácida total',
            'Fosfatasa ácida prostática', 'Gamma GT', 'Colinesterasa', 'LDH', 'Amilasa',
            'Lipasa', 'CK', 'Aldolasa',
        ],
        'hematologia' => [
            'Hemograma completo', 'Hemoglobina y hematocrito',
            'Eritrosedimentación (VSG)', 'Reticulocitos', 'Grupo sanguíneo y factor Rh',
            'Ferritina', 'Transferrina', 'Coombs directo', 'Coombs indirecto',
            'Plasmodium antígeno', 'Hematozoario en gota gruesa',
        ],
        'endocrinos' => [
            ['HCG beta cualitativo', 'sensible' => true],
            ['HCG beta cuantitativo', 'sensible' => true],
            'FSH', 'LH', 'Prolactina', 'Progesterona', '17-beta estradiol', 'Testosterona',
            'T3 total', 'T3 libre', 'T4 total', 'T4 libre', 'TSH', 'TPO', 'TRAB',
            'Cortisol', 'Insulina',
        ],
        'marcadores_tumorales' => [
            'PSA total', 'PSA libre', 'CEA', 'AFP', 'CA 125 (ovario y útero)',
            'CA 15-3 (mama)', 'CA 19-9 (páncreas)', 'CA 72-4 (digestivo)',
            'Beta 2 microglobulina', 'CYFRA 21-1 (pulmón)', 'Enolasa NSE (pulmón)',
        ],
        'electrolitos' => [
            'Sodio', 'Potasio', 'Cloro', 'Calcio', 'Calcio iónico', 'Fósforo', 'Magnesio',
            'Litio', 'Amonio', 'Plomo',
        ],
        'orina' => [
            'Elemental y microscópico (físico-químico y sedimento)',
            ['Prueba de embarazo', 'sensible' => true],
            'Sodio en orina', 'Potasio en orina', 'Cloro en orina', 'Albúmina en orina',
            'Microalbuminuria', 'Proteinuria', 'Creatinina en orina',
            'Depuración de creatinina', 'Urea en orina',
        ],
        'heces' => [
            'Antígeno de H. pylori', 'Coproparasitario', 'Coproparasitario por concentración',
            'Sangre oculta', 'Rotavirus y adenovirus',
            ['Coproparasitario seriado', 'nota' => 'Número de muestras'],
            'Polimorfonucleares',
        ],
        'serologia' => [
            'VDRL', 'Reacción de Widal y Weil-Felix', 'PCR turbidimétrico',
            'Factor reumatoideo turbidimétrico', 'ASTO turbidimétrico',
        ],
        'marcadores_cardiacos' => [
            'Troponina I', 'Troponina T', 'Mioglobina', 'CK-MB',
        ],
        'radiografias' => [
            'Rx estándar de tórax', 'Rx de cráneo',
            ['Rx de miembro superior', 'nota' => 'Lado y segmento'],
            ['Rx de miembro inferior', 'nota' => 'Lado y segmento'],
            'Rx de columna cervical', 'Rx de columna dorsal', 'Rx de columna lumbar',
            'Rx periapical', 'Rx panorámica',
        ],
        'ecografias' => [
            'Eco abdominal', 'Eco pélvico', 'Eco transvaginal', 'Eco de mamas',
            'Eco prostático', 'Eco tiroideo',
        ],
        'ocupacionales' => [
            'Audiometría tonal', 'Espirometría', 'Electrocardiograma',
        ],
    ];

    public function up(): void
    {
        Schema::create('catalogo_examenes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('area', 30);
            // La posición dentro del área: la de la hoja, y al final lo nuevo.
            $table->unsignedSmallInteger('orden');
            $table->string('etiqueta_nota', 40)->nullable();
            // La preparación de cada examen queda para después (P4): el médico
            // escribe las indicaciones generales en la orden.
            $table->text('indicaciones')->nullable();
            $table->boolean('sensible')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Un mismo nombre puede estar en dos áreas («Sodio» en sangre y en
            // orina), pero no dos veces en la misma.
            $table->unique(['area', 'nombre']);
            $table->index(['area', 'orden']);
        });

        $ahora = now();
        $filas = [];

        foreach (self::CATALOGO as $area => $examenes) {
            foreach (array_values($examenes) as $i => $examen) {
                $examen = is_array($examen) ? $examen : [$examen];

                $filas[] = [
                    'nombre'        => $examen[0],
                    'area'          => $area,
                    'orden'         => $i + 1,
                    'etiqueta_nota' => $examen['nota'] ?? null,
                    'sensible'      => $examen['sensible'] ?? false,
                    'activo'        => true,
                    'created_at'    => $ahora,
                    'updated_at'    => $ahora,
                ];
            }
        }

        DB::table('catalogo_examenes')->insert($filas);
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_examenes');
    }
};

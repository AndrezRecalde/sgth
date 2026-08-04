<?php

namespace Database\Seeders;

use App\Enums\TipoNombramiento;
use App\Enums\TipoProcesoConvocatoria;
use App\Models\Seleccion\Convocatoria;
use Illuminate\Database\Seeder;

/**
 * Los cuatro contenedores permanentes de reclutamiento express, uno por
 * modalidad de vinculación que no pasa por concurso de méritos y oposición.
 *
 * Son fijos: no se crean por año ni por proceso. El año de cada aspirante sale
 * de su fecha de inscripción, y por eso las fechas del contenedor son un rango
 * abierto que solo existe para satisfacer las columnas NOT NULL heredadas de
 * las convocatorias formales.
 */
class ContenedorExpressSeeder extends Seeder
{
    public function run(): void
    {
        $contenedores = [
            [
                'codigo'      => 'EXP-PROVISIONAL',
                'titulo'      => 'Reclutamiento — Nombramiento Provisional',
                'descripcion' => 'Contenedor permanente de aspirantes a nombramiento provisional. '
                    .'Los aspirantes se agregan individualmente y se evalúan uno por uno.',
                'tipo_nombramiento_previsto' => TipoNombramiento::PROVISIONAL->value,
            ],
            [
                'codigo'      => 'EXP-OCASIONAL',
                'titulo'      => 'Reclutamiento — Servicios Ocasionales',
                'descripcion' => 'Contenedor permanente de aspirantes a contrato de servicios ocasionales.',
                'tipo_nombramiento_previsto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
            ],
            [
                'codigo'      => 'EXP-PROFESIONALES',
                'titulo'      => 'Reclutamiento — Servicios Profesionales',
                'descripcion' => 'Contenedor permanente de aspirantes a contrato de servicios profesionales. '
                    .'El contrato dura el año calendario en que se suscribe.',
                'tipo_nombramiento_previsto' => TipoNombramiento::SERVICIOS_PROFESIONALES->value,
            ],
            [
                'codigo'      => 'EXP-CODIGO-TRABAJO',
                'titulo'      => 'Reclutamiento — Código del Trabajo (Obreros)',
                'descripcion' => 'Contenedor permanente de aspirantes a contrato bajo Código del Trabajo.',
                'tipo_nombramiento_previsto' => TipoNombramiento::CODIGO_TRABAJO->value,
            ],
        ];

        foreach ($contenedores as $datos) {
            Convocatoria::updateOrCreate(
                ['codigo' => $datos['codigo']],
                [
                    ...$datos,
                    'puesto_id'    => null,
                    'tipo'         => 'externa',
                    'tipo_proceso' => TipoProcesoConvocatoria::EXPRESS->value,
                    'es_contenedor_permanente' => true,
                    'estado'       => 'publicada',
                    'vacantes'     => 1,
                    // Rango abierto: el contenedor no tiene período, lo tiene
                    // cada aspirante en su fecha de inscripción.
                    'fecha_inicio' => '2020-01-01',
                    'fecha_fin'    => '2099-12-31',
                ]
            );
        }
    }
}

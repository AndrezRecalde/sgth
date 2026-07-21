<?php

namespace Database\Seeders;

use App\Enums\FaseProgramaDrogas;
use App\Models\Sso\ProgramaDrogaActividad;
use Illuminate\Database\Seeder;

/**
 * Matriz de actividades del Programa de prevención integral del uso y consumo de alcohol,
 * tabaco u otras drogas en espacios laborales — Instructivo MDT-MSP (Acuerdo Interministerial
 * Nro. MDT-MSP-2019-038, Registro Oficial Nro. 114 del 06/01/2020), secciones 2 y 5.
 */
class ProgramaDrogasActividadesSeeder extends Seeder
{
    public function run(): void
    {
        $actividades = [
            FaseProgramaDrogas::FASE_1_PREPARACION->value => [
                'Presentar el compromiso oficial por escrito de la Máxima Autoridad o Representante Legal para la implementación del programa',
                'Identificar los factores a investigar, herramientas a aplicar y cronograma de implementación del programa',
            ],
            FaseProgramaDrogas::FASE_2_EQUIPO_MULTIDISCIPLINARIO->value => [
                'Conformar el equipo multidisciplinario responsable de la implementación del programa de prevención (médico de SST, técnico de seguridad y, de contarse, psicología clínica, trabajo social, enfermería y talento humano)',
            ],
            FaseProgramaDrogas::FASE_3_SOCIALIZACION->value => [
                'Comunicar a la población trabajadora la implementación del programa de prevención integral de drogas y sus beneficios',
                'Informar sobre la aplicación del diagnóstico general mediante un instrumento de tamizaje validado nacional o internacionalmente',
                'Motivar la participación activa de la población trabajadora en las actividades del programa',
            ],
            FaseProgramaDrogas::FASE_4_DIAGNOSTICO->value => [
                'Aplicar la herramienta de tamizaje (ASSIST) a la población trabajadora, manteniendo confidencialidad y anonimato',
                'Analizar los resultados obtenidos del diagnóstico general',
            ],
            FaseProgramaDrogas::FASE_5_ACTUACION->value => [
                'Realizar procesos de sensibilización y concientización sobre prevención integral del uso y consumo de drogas',
                'Realizar campañas informativas sobre el desarrollo de prácticas de vida saludable',
                'Implementar la señalética informativa, obligatoria, preventiva y prohibitiva sobre consumo de drogas en los espacios laborales',
                'Gestionar la certificación de "Espacio 100% libre de humo de tabaco" emitida por el Ministerio de Salud Pública',
                'Realizar talleres, conversatorios, video foros o chats sobre prevención integral del uso y consumo de alcohol, tabaco u otras drogas',
                'Realizar talleres sobre el fortalecimiento de factores protectores individuales, colectivos y sociales',
                'Realizar actividades físico-recreativas para promover el buen uso del ocio y tiempo libre',
                'Incorporar medidas de control para prevenir situaciones de riesgo que afecten la integridad de la población trabajadora',
                'Derivar a la población trabajadora con presunción de consumo problemático de drogas a atención especializada (Red Pública Integral de Salud)',
                'Elaborar los procedimientos y/o protocolos para la reinserción laboral de la población trabajadora en tratamiento',
                'Gestionar la reinserción laboral de la población trabajadora que culminó el tratamiento por consumo de drogas',
            ],
            FaseProgramaDrogas::FASE_6_SEGUIMIENTO->value => [
                'Ejecutar el seguimiento del cumplimiento del tratamiento de la población trabajadora que accedió a atención especializada',
                'Evaluar la efectiva ejecución de las acciones preventivas (satisfacción del personal, tasa de ausentismo, productividad)',
                'Elaborar el informe final de ejecución del programa de prevención y comunicar los resultados',
            ],
        ];

        foreach ($actividades as $fase => $nombres) {
            foreach ($nombres as $nombre) {
                ProgramaDrogaActividad::firstOrCreate([
                    'fase' => $fase,
                    'nombre' => $nombre,
                ]);
            }
        }
    }
}

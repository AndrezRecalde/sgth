<?php
namespace Database\Seeders;

use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnidadAdministrativaSeeder extends Seeder
{
    public function run(): void
    {
        // ── IDs de tipos de unidad (del TipoUnidadSeeder) ──
        $GOBERNANTE  = '11111111-1111-1111-1111-111111111111';
        $APOYO       = '22222222-2222-2222-2222-222222222222';
        $ASESOR      = '33333333-3333-3333-3333-333333333333';
        $AGREGADOR   = '44444444-4444-4444-4444-444444444444';

        // ── RAÍZ ─────────────────────────────────────────
        $gadpe = $this->crear([
            'nombre'          => 'GADPE',
            'descripcion'     => 'Gobierno Autónomo Descentralizado de la Provincia de Esmeraldas',
            'tipo_unidad_id'  => $GOBERNANTE,
            'unidad_padre_id' => null,
        ]);

        // ══════════════════════════════════════════════════
        // PROCESOS GOBERNANTES
        // ══════════════════════════════════════════════════
        $prefectura = $this->crear([
            'nombre'          => 'Prefectura Provincial',
            'tipo_unidad_id'  => $GOBERNANTE,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $viceprefectura = $this->crear([
            'nombre'          => 'Viceprefectura Provincial',
            'tipo_unidad_id'  => $GOBERNANTE,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // ══════════════════════════════════════════════════
        // PROCESOS HABILITANTES DE ASESORÍA
        // ══════════════════════════════════════════════════
        $coordinacion = $this->crear([
            'nombre'          => 'Gestión de Coordinación Institucional',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $auditoria = $this->crear([
            'nombre'          => 'Unidad de Gestión de Auditoría Interna',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $procuraduria = $this->crear([
            'nombre'          => 'Gestión de Procuraduría Síndica',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Procuraduría
        $this->crear(['nombre' => 'Patrocinio Jurídico',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $procuraduria->id]);
        $this->crear(['nombre' => 'Asesoría Legal',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $procuraduria->id]);
        $this->crear(['nombre' => 'Coactiva',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $procuraduria->id]);
        $this->crear(['nombre' => 'Archivo, Manejo y Control Documental Jurídico',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $procuraduria->id]);

        $comunicacion = $this->crear([
            'nombre'          => 'Gestión de Comunicación Social',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Comunicación
        $this->crear(['nombre' => 'Comunicación Organizacional',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $comunicacion->id]);
        $this->crear(['nombre' => 'Logística Comunicacional',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $comunicacion->id]);
        $this->crear(['nombre' => 'Comunicación Estratégica',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $comunicacion->id]);
        $this->crear(['nombre' => 'Relaciones Públicas y Eventos',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $comunicacion->id]);
        $this->crear(['nombre' => 'Producción Audiovisual y Diseño Gráfico',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $comunicacion->id]);

        $planificacion = $this->crear([
            'nombre'          => 'Gestión de Planificación',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Planificación
        $this->crear(['nombre' => 'Planificación Territorial',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $planificacion->id]);
        $this->crear(['nombre' => 'Planificación Institucional',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $planificacion->id]);
        $this->crear(['nombre' => 'Proyectos Estratégicos y Operativos',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $planificacion->id]);

        $accionSocial = $this->crear([
            'nombre'          => 'Gestión de Acción Social, Inclusión y Participación',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Acción Social
        $this->crear(['nombre' => 'Participación Ciudadana',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $accionSocial->id]);
        $this->crear(['nombre' => 'Igualdades y Derechos',
            'tipo_unidad_id' => $ASESOR, 'unidad_padre_id' => $accionSocial->id]);

        $calidad = $this->crear([
            'nombre'          => 'Unidad de Gestión de Calidad',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $riesgos = $this->crear([
            'nombre'          => 'Unidad de Gestión de Riesgos de Desastres',
            'tipo_unidad_id'  => $ASESOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // ══════════════════════════════════════════════════
        // PROCESOS HABILITANTES DE APOYO
        // ══════════════════════════════════════════════════
        $talentoHumano = $this->crear([
            'nombre'          => 'Gestión de Talento Humano y Riesgos Laborales',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Talento Humano
        $this->crear(['nombre' => 'Administración del Talento Humano',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $talentoHumano->id]);
        $this->crear(['nombre' => 'Nómina y Remuneraciones',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $talentoHumano->id]);
        $this->crear(['nombre' => 'Seguridad y Salud Ocupacional',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $talentoHumano->id]);
        $this->crear(['nombre' => 'Bienestar Social',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $talentoHumano->id]);
        $this->crear(['nombre' => 'Dispensario Médico',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $talentoHumano->id]);

        $financiera = $this->crear([
            'nombre'          => 'Gestión Financiera',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Financiera
        $this->crear(['nombre' => 'Presupuesto',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $financiera->id]);
        $this->crear(['nombre' => 'Contabilidad',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $financiera->id]);
        $this->crear(['nombre' => 'Tesorería',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $financiera->id]);
        $this->crear(['nombre' => 'Rentas',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $financiera->id]);

        $administrativa = $this->crear([
            'nombre'          => 'Gestión Administrativa',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Administrativa
        $this->crear(['nombre' => 'Servicios Generales y Transporte',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $administrativa->id]);
        $this->crear(['nombre' => 'Activos Fijos y Bodega',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $administrativa->id]);
        $this->crear(['nombre' => 'Guardalmacén',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $administrativa->id]);

        $fiscalizacion = $this->crear([
            'nombre'          => 'Gestión de Fiscalización',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $secretaria = $this->crear([
            'nombre'          => 'Gestión de Secretaría General',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Secretaría
        $this->crear(['nombre' => 'Archivo Central y Gestión Documental',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $secretaria->id]);

        $tic = $this->crear([
            'nombre'          => 'Gestión de Tecnologías de la Información y Comunicación',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de TIC
        $this->crear(['nombre' => 'Infraestructura y Redes',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $tic->id]);
        $this->crear(['nombre' => 'Desarrollo de Software',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $tic->id]);
        $this->crear(['nombre' => 'Soporte Técnico',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $tic->id]);
        $this->crear(['nombre' => 'Seguridad de la Información',
            'tipo_unidad_id' => $APOYO, 'unidad_padre_id' => $tic->id]);

        $contratacion = $this->crear([
            'nombre'          => 'Unidad de Gestión de Contratación Pública',
            'tipo_unidad_id'  => $APOYO,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // ══════════════════════════════════════════════════
        // PROCESOS AGREGADORES DE VALOR
        // ══════════════════════════════════════════════════
        $vial = $this->crear([
            'nombre'          => 'Gestión de Infraestructura Vial',
            'tipo_unidad_id'  => $AGREGADOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Infraestructura Vial
        $this->crear(['nombre' => 'Planificación Vial',
            'tipo_unidad_id' => $AGREGADOR, 'unidad_padre_id' => $vial->id]);
        $this->crear(['nombre' => 'Construcción y Mantenimiento Vial',
            'tipo_unidad_id' => $AGREGADOR, 'unidad_padre_id' => $vial->id]);
        $this->crear(['nombre' => 'Maquinaria y Equipo',
            'tipo_unidad_id' => $AGREGADOR, 'unidad_padre_id' => $vial->id]);

        $fomento = $this->crear([
            'nombre'          => 'Gestión de Fomento y Desarrollo Productivo',
            'tipo_unidad_id'  => $AGREGADOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $cuencas = $this->crear([
            'nombre'          => 'Gestión de Cuencas, Riego y Drenaje',
            'tipo_unidad_id'  => $AGREGADOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        $ambiental = $this->crear([
            'nombre'          => 'Gestión Ambiental',
            'tipo_unidad_id'  => $AGREGADOR,
            'unidad_padre_id' => $gadpe->id,
        ]);

        // Subprocesos de Gestión Ambiental
        $this->crear(['nombre' => 'Calidad Ambiental',
            'tipo_unidad_id' => $AGREGADOR, 'unidad_padre_id' => $ambiental->id]);
        $this->crear(['nombre' => 'Áreas Protegidas y Biodiversidad',
            'tipo_unidad_id' => $AGREGADOR, 'unidad_padre_id' => $ambiental->id]);

        $cooperacion = $this->crear([
            'nombre'          => 'Gestión de Relaciones Internacionales y Cooperación',
            'tipo_unidad_id'  => $AGREGADOR,
            'unidad_padre_id' => $gadpe->id,
        ]);
    }

    private function crear(array $datos): UnidadAdministrativa
    {
        return UnidadAdministrativa::firstOrCreate(
            ['nombre' => $datos['nombre']],
            array_merge($datos, [
                'codigo'          => strtoupper(substr(\Illuminate\Support\Str::slug($datos['nombre']), 0, 15)) . '-' . rand(10, 99),
                'nivel'           => 1,
                'descripcion'     => $datos['descripcion'] ?? null,
                'unidad_padre_id' => $datos['unidad_padre_id'] ?? null,
            ])
        );
    }
}

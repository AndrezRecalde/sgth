<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            // Sección A — Datos personales completos
            $table->date('fecha_nacimiento')->nullable()->after('segundo_apellido');
            $table->string('genero')->nullable()->after('fecha_nacimiento');
            $table->string('estado_civil')->nullable()->after('genero');
            $table->string('tipo_sangre')->nullable()->after('estado_civil');
            $table->boolean('es_extranjero')->default(false)->after('tipo_sangre');
            $table->string('nacionalidad')->nullable()->after('es_extranjero');
            $table->string('pais_origen')->nullable()->after('nacionalidad');
            $table->string('provincia_nacimiento')->nullable()->after('pais_origen');
            $table->string('ciudad_nacimiento')->nullable()->after('provincia_nacimiento');

            // Sección B — Documentos de identidad y ciudadanía
            $table->string('numero_papeleta_votacion')->nullable()->after('ciudad_nacimiento');
            $table->string('pasaporte_numero')->nullable()->after('numero_papeleta_votacion');
            $table->date('pasaporte_vencimiento')->nullable()->after('pasaporte_numero');

            // Sección C — Contacto
            $table->string('telefono_celular')->nullable()->after('pasaporte_vencimiento');
            $table->string('telefono_convencional')->nullable()->after('telefono_celular');
            $table->string('correo_institucional')->unique()->nullable()->after('telefono_convencional');
            $table->string('correo_personal')->nullable()->after('correo_institucional');
            $table->string('direccion_domicilio')->nullable()->after('correo_personal');
            $table->string('provincia_domicilio')->nullable()->after('direccion_domicilio');
            $table->string('ciudad_domicilio')->nullable()->after('provincia_domicilio');

            // Sección D — Discapacidad (CONADIS)
            $table->boolean('tiene_discapacidad')->default(false)->after('ciudad_domicilio');
            $table->string('tipo_discapacidad')->nullable()->after('tiene_discapacidad');
            $table->decimal('porcentaje_discapacidad', 5, 2)->nullable()->after('tipo_discapacidad');
            $table->string('numero_carnet_conadis')->nullable()->after('porcentaje_discapacidad');
            $table->string('carnet_conadis_ruta')->nullable()->after('numero_carnet_conadis');
            $table->date('carnet_conadis_vencimiento')->nullable()->after('carnet_conadis_ruta');

            // Sección E — Enfermedad catastrófica
            $table->boolean('tiene_enfermedad_catastrofica')->default(false)->after('carnet_conadis_vencimiento');
            $table->string('tipo_enfermedad_catastrofica')->nullable()->after('tiene_enfermedad_catastrofica');
            $table->string('enfermedad_catastrofica_certificado_ruta')->nullable()->after('tipo_enfermedad_catastrofica');

            // Sección F — Datos laborales e institucionales
            $table->string('tipo_nombramiento')->nullable()->after('enfermedad_catastrofica_certificado_ruta');
            $table->string('numero_contrato')->nullable()->after('tipo_nombramiento');
            $table->date('fecha_ingreso_institucion')->nullable()->after('numero_contrato');
            $table->date('fecha_ingreso_sector_publico')->nullable()->after('fecha_ingreso_institucion');
            $table->date('fecha_nombramiento')->nullable()->after('fecha_ingreso_sector_publico');
            $table->date('fecha_inicio_ultimo_contrato')->nullable()->after('fecha_nombramiento');
            $table->date('fecha_fin_ultimo_contrato')->nullable()->after('fecha_inicio_ultimo_contrato');

            // Sección G — Biométrico
            $table->string('codigo_marcacion', 10)->nullable()->after('fecha_fin_ultimo_contrato');
            $table->index('codigo_marcacion');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropIndex(['codigo_marcacion']);

            $table->dropColumn([
                // Sección A
                'fecha_nacimiento', 'genero', 'estado_civil', 'tipo_sangre', 'es_extranjero', 
                'nacionalidad', 'pais_origen', 'provincia_nacimiento', 'ciudad_nacimiento',
                // Sección B
                'numero_papeleta_votacion', 'pasaporte_numero', 'pasaporte_vencimiento',
                // Sección C
                'telefono_celular', 'telefono_convencional', 'correo_institucional', 'correo_personal',
                'direccion_domicilio', 'provincia_domicilio', 'ciudad_domicilio',
                // Sección D
                'tiene_discapacidad', 'tipo_discapacidad', 'porcentaje_discapacidad', 'numero_carnet_conadis', 
                'carnet_conadis_ruta', 'carnet_conadis_vencimiento',
                // Sección E
                'tiene_enfermedad_catastrofica', 'tipo_enfermedad_catastrofica', 'enfermedad_catastrofica_certificado_ruta',
                // Sección F
                'tipo_nombramiento', 'numero_contrato', 'fecha_ingreso_institucion', 'fecha_ingreso_sector_publico',
                'fecha_nombramiento', 'fecha_inicio_ultimo_contrato', 'fecha_fin_ultimo_contrato',
                // Sección G
                'codigo_marcacion'
            ]);
        });
    }
};

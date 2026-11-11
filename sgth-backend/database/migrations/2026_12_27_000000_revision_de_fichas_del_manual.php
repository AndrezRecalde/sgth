<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| La revisión de las fichas del Manual de Puestos 2018-2019
| (docs/diseno-manual-de-puestos.md, P4; PR 12).
|
| - `fichas_manual_puestos`: el puesto en que TH cargó cada ficha, quién y
|   cuándo. Un puesto recibe una sola ficha: el perfil es uno por puesto.
| - `equivalencias_unidades_manual`: a qué unidad de hoy corresponde cada
|   gestión del manual. La lleva TH; mientras no la elige, se sugiere por el
|   nombre.
| - `puesto_actividades`: la descripción pasa a texto y los conocimientos a
|   1000 caracteres. El límite de 200 venía de las actividades cortas de la
|   ficha médica ocupacional, y el manual describe 227 actividades más largas
|   (hasta 2969 caracteres las atribuciones del Prefecto).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_manual_puestos', function (Blueprint $table) {
            $table->foreignId('puesto_id')->nullable()->unique()->constrained('puestos')->nullOnDelete();
            $table->foreignId('cargada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cargada_en')->nullable();
        });

        Schema::create('equivalencias_unidades_manual', function (Blueprint $table) {
            $table->id();
            // El nombre de la gestión en el manual, normalizado: sin tildes,
            // en minúsculas y sin signos. Las erratas («Fiiscalizacón») son
            // claves aparte, cada una con su unidad.
            $table->string('gestion', 300)->unique();
            $table->foreignId('unidad_administrativa_id')->constrained('unidades_administrativas')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('puesto_actividades', function (Blueprint $table) {
            $table->text('descripcion')->change();
            $table->string('conocimientos', 1000)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('puesto_actividades', function (Blueprint $table) {
            $table->string('descripcion', 200)->change();
            $table->string('conocimientos', 500)->nullable()->change();
        });

        Schema::dropIfExists('equivalencias_unidades_manual');

        Schema::table('fichas_manual_puestos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cargada_por');
            $table->dropConstrainedForeignId('puesto_id');
            $table->dropColumn('cargada_en');
        });
    }
};

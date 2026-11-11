<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| La tabla de staging de la importación del Manual de Puestos 2018-2019
| (docs/diseno-manual-de-puestos.md, P4; PR 11). La llena
| `sgth:manual:importar` con el JSON del extractor, una fila por ficha.
|
| - `ficha`: lo que trajo el extractor, tal cual.
| - `propuesta`: el perfil en los términos del SGTH (niveles de instrucción,
|   meses de experiencia, competencias del diccionario con su número).
| - `avisos`: lo que TH tiene que mirar antes de confirmarla.
|
| La identificación va también en columnas, para emparejar cada ficha con su
| puesto en la revisión (PR 12). Volver a importar actualiza la fila por su
| `archivo`.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichas_manual_puestos', function (Blueprint $table) {
            $table->id();
            $table->string('archivo', 300)->unique();
            $table->string('proceso', 150);
            $table->string('carpeta', 300);
            $table->string('denominacion', 200)->nullable();
            $table->string('unidad', 300)->nullable();
            $table->string('grupo_ocupacional', 100)->nullable();
            $table->unsignedSmallInteger('grado')->nullable();
            $table->unsignedSmallInteger('plazas')->default(1);
            $table->jsonb('ficha');
            $table->jsonb('propuesta');
            $table->jsonb('avisos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas_manual_puestos');
    }
};

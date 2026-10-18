<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las noticias y comunicados de Talento Humano
 * (docs/diseno-noticias-comunicados.md, 7.2; primera entrega).
 *
 * Se guarda solo el estado que decide una persona —borrador, publicado,
 * retirado—; programado y vencido se deducen de las fechas al consultar, para
 * que no dependan de una tarea programada que puede no correr.
 *
 * Los destinatarios se calculan al leer: quien ingresa después de publicado
 * ve lo vigente de su unidad. Lo que sí se congela al publicar son las
 * dependencias de cada unidad elegida, para que un cambio del orgánico no
 * cambie en silencio a quién iba dirigido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicados', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);
            $table->string('titulo', 150);
            $table->string('resumen', 300)->nullable();
            // HTML ya limpio (HtmlEnriquecido): se filtra al entrar, no al pintar.
            $table->text('cuerpo')->nullable();
            // El número de la circular o el memorando; solo en comunicados.
            $table->string('referencia', 100)->nullable();
            $table->string('portada_path')->nullable();
            $table->string('estado', 20);
            $table->string('audiencia', 20);
            // Los regímenes a los que va; vacío = cualquiera.
            $table->jsonb('regimenes')->nullable();
            $table->timestamp('publicado_desde')->nullable();
            $table->timestamp('publicado_hasta')->nullable();
            $table->boolean('fijado')->default(false);
            $table->boolean('requiere_confirmacion')->default(false);
            // El original, cuando se corrige uno publicado retirándolo y duplicándolo.
            $table->foreignId('duplicado_de_id')->nullable()->constrained('comunicados')->nullOnDelete();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publicado_at')->nullable();
            $table->foreignId('retirado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retirado_at')->nullable();
            $table->string('motivo_retiro', 500)->nullable();
            $table->timestamps();

            $table->index(['estado', 'publicado_desde']);
        });

        // Las unidades a las que va. `seleccionada` distingue las que eligió
        // Talento Humano de las que entraron al publicar como dependencias de
        // una elegida con «incluir dependencias».
        Schema::create('comunicado_unidades', function (Blueprint $table) {
            $table->foreignId('comunicado_id')->constrained('comunicados')->cascadeOnDelete();
            $table->foreignId('unidad_administrativa_id')->constrained('unidades_administrativas')->cascadeOnDelete();
            $table->boolean('seleccionada')->default(true);
            $table->boolean('incluye_dependencias')->default(false);

            $table->primary(['comunicado_id', 'unidad_administrativa_id']);
        });

        Schema::create('comunicado_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comunicado_id')->constrained('comunicados')->cascadeOnDelete();
            $table->string('nombre_original', 200);
            $table->string('path');
            $table->unsignedInteger('tamano');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();

            $table->index(['comunicado_id', 'orden']);
        });

        // Quién abrió cada publicación y quién confirmó haberla leído. Una
        // fila por usuario: se crea al abrir el detalle en el Portal.
        Schema::create('comunicado_lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comunicado_id')->constrained('comunicados')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('leido_at');
            $table->timestamp('confirmado_at')->nullable();

            $table->unique(['comunicado_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicado_lecturas');
        Schema::dropIfExists('comunicado_adjuntos');
        Schema::dropIfExists('comunicado_unidades');
        Schema::dropIfExists('comunicados');
    }
};

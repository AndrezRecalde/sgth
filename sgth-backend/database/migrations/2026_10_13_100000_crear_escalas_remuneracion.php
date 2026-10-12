<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La nueva escala de remuneraciones de la LOSEP (diseño de Acciones de
 * Personal, fase 7.3b).
 *
 * La RMU de cada grado vivía solo en el catálogo (`grupos_ocupacionales.rmu`),
 * sin pantalla para cambiarla ni constancia de cuándo cambió. Mientras la
 * nómina leía la del puesto, tocar el catálogo cambiaba el sueldo sin acto;
 * cuando lea la del vínculo (7.3c), ya no: la escala tiene que escribirla en
 * cada contrato.
 *
 * La escala es un acto general —la resolución que la fija—, sin acción de
 * personal por servidor mientras Talento Humano no diga otra cosa. Guarda la
 * RMU de antes y la nueva de cada grado, y la remuneración de antes y la nueva
 * de cada vínculo, para revertirla y para las novedades de nómina. Cada
 * vínculo deja su constancia en la bitácora.
 */
return new class extends Migration
{
    private const TIPOS_DE_BITACORA = [
        'contrato_registrado', 'subrogacion_finalizada', 'subrogacion_cancelada', 'titular_cesado',
        'cambio_puesto', 'cambio_regimen', 'egreso', 'fin_del_periodo_fijado',
    ];

    public function up(): void
    {
        Schema::create('escalas_remuneracion', function (Blueprint $table) {
            $table->id();
            // La resolución que la fija.
            $table->string('instrumento', 120);
            $table->date('fecha_instrumento');
            $table->date('rige_desde');
            $table->string('dictamen_presupuestario_ref', 120);
            $table->text('descripcion')->nullable();
            $table->foreignId('registrada_por')->constrained('users');
            $table->timestamp('registrada_en');
            $table->foreignId('revertida_por')->nullable()->constrained('users');
            $table->timestamp('revertida_en')->nullable();
            $table->text('motivo_reversion')->nullable();
            $table->timestamps();
        });

        Schema::create('escala_remuneracion_grados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escala_id')->constrained('escalas_remuneracion')->cascadeOnDelete();
            $table->foreignId('grupo_ocupacional_id')->constrained('grupos_ocupacionales');
            $table->decimal('rmu_anterior', 10, 2);
            $table->decimal('rmu_nueva', 10, 2);
            $table->timestamps();

            $table->unique(['escala_id', 'grupo_ocupacional_id']);
        });

        Schema::create('escala_remuneracion_vinculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escala_id')->constrained('escalas_remuneracion')->cascadeOnDelete();
            $table->foreignId('contrato_id')->constrained('contratos_servidor');
            $table->foreignId('servidor_id')->constrained('servidores');
            $table->foreignId('grupo_ocupacional_id')->constrained('grupos_ocupacionales');
            $table->decimal('remuneracion_anterior', 10, 2)->nullable();
            $table->decimal('remuneracion_nueva', 10, 2);
            $table->timestamps();

            $table->index('contrato_id');
        });

        $this->reemplazarCheck([...self::TIPOS_DE_BITACORA, 'escala_aplicada', 'escala_revertida']);
    }

    public function down(): void
    {
        DB::table('eventos_vinculo')->whereIn('tipo', ['escala_aplicada', 'escala_revertida'])->delete();
        $this->reemplazarCheck(self::TIPOS_DE_BITACORA);

        Schema::dropIfExists('escala_remuneracion_vinculos');
        Schema::dropIfExists('escala_remuneracion_grados');
        Schema::dropIfExists('escalas_remuneracion');
    }

    /** @param  list<string>  $tipos */
    private function reemplazarCheck(array $tipos): void
    {
        $lista = implode(', ', array_map(fn (string $t) => "'{$t}'", $tipos));

        DB::statement('ALTER TABLE eventos_vinculo DROP CONSTRAINT IF EXISTS eventos_vinculo_tipo_check');
        DB::statement("ALTER TABLE eventos_vinculo ADD CONSTRAINT eventos_vinculo_tipo_check CHECK (tipo IN ({$lista}))");
    }
};

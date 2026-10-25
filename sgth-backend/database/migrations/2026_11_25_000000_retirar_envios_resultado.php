<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * El servidor ya no envía resultados desde el Portal (2026-10-25): los suben
 * solo el médico y el odontólogo, y «Mis exámenes» se retiró. La tabla de la
 * fase 6 (2026_11_20_000000) se va.
 *
 * Un envío validado no se pierde: al validarlo nació un resultado de la
 * historia clínica que usa el mismo archivo, y ese resultado se queda. Se
 * borran solo los archivos que ningún resultado usa —lo retirado, lo
 * rechazado y lo que nadie atendió—: eran del servidor, no de la historia,
 * y ya no hay pantalla que los enseñe.
 */
return new class extends Migration
{
    private const DISCO = 'local';

    public function up(): void
    {
        if (! Schema::hasTable('envios_resultado')) {
            return;
        }

        $enUso = DB::table('resultados_medicos')->pluck('archivo')->filter()->flip();
        $disco = Storage::disk(self::DISCO);

        DB::table('envios_resultado')->orderBy('id')->pluck('archivo')
            ->reject(fn (string $archivo) => $enUso->has($archivo))
            ->each(fn (string $archivo) => $disco->delete($archivo));

        Schema::drop('envios_resultado');
    }

    /** La tabla vuelve vacía: los envíos y sus archivos no se recuperan. */
    public function down(): void
    {
        Schema::create('envios_resultado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_examen_id')->constrained('ordenes_examen');
            $table->foreignId('enviado_por')->constrained('users');
            $table->json('items');
            $table->date('fecha_resultado');
            $table->string('comentario', 300)->nullable();
            $table->string('archivo');
            $table->string('estado', 20)->default('por_validar');
            $table->string('motivo_rechazo', 500)->nullable();
            $table->foreignId('atendido_por')->nullable()->constrained('users');
            $table->timestamp('atendido_en')->nullable();
            $table->foreignId('resultado_medico_id')
                  ->nullable()
                  ->constrained('resultados_medicos')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }
};

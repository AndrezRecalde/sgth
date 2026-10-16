<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La «Solicitud de materiales informáticos a bodega» (fase 5.4c): el pedido
 * que TIC le hace a Gestión Administrativa por lo que despacha Bodega, con el
 * formato del Excel de hoy. Sale de un ticket de la categoría nueva
 * «Consumibles» [P3, P16] o, hasta que arranque la mesa de ayuda, del módulo de
 * consumibles. Cuando Bodega despacha, TIC anota la cantidad despachada: esa es
 * la entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        // La categoría que pide consumibles: lleva equipo, pero no deja un
        // mantenimiento en la ficha al resolverse.
        Schema::table('categorias_ticket', function (Blueprint $table) {
            $table->boolean('es_consumibles')->default(false);
        });
        $this->sembrarCategoria();

        Schema::create('solicitudes_bodega', function (Blueprint $table) {
            $table->id();
            // Correlativo por año: SB-2026-0001.
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('numero');
            $table->date('fecha');
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            // De parte de quién, con su unidad de ese momento.
            $table->foreignId('servidor_id')->constrained('servidores');
            $table->foreignId('unidad_administrativa_id')->nullable()->constrained('unidades_administrativas');
            $table->foreignId('bien_informatico_id')->nullable()->constrained('bienes_informaticos');
            // «Agradeceré disponga al Departamento de Bodega el despacho de…»: para qué es.
            $table->string('motivo', 500);
            // pendiente, despachada, anulada
            $table->string('estado', 12)->default('pendiente');
            // Los firmantes, sellados al generarla: el PDF se reimprime igual aunque cambien los jefes.
            $table->json('firmantes');
            $table->date('fecha_despacho')->nullable();
            $table->foreignId('despachada_por')->nullable()->constrained('users');
            $table->timestamp('anulada_en')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users');
            $table->string('motivo_anulacion', 300)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['anio', 'numero']);
            $table->index(['estado', 'fecha']);
        });

        Schema::create('solicitud_bodega_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes_bodega')->cascadeOnDelete();
            $table->foreignId('consumible_id')->constrained('consumibles');
            $table->unsignedInteger('cantidad_solicitada');
            $table->unsignedInteger('cantidad_despachada')->nullable();
            $table->timestamps();
            $table->unique(['solicitud_id', 'consumible_id']);
        });

        Schema::table('movimientos_consumible', function (Blueprint $table) {
            // Lo que entró o salió por el despacho de una solicitud.
            $table->foreignId('solicitud_bodega_id')->nullable()->constrained('solicitudes_bodega')->nullOnDelete();
        });
        // «solicitud_bodega» no cabía en 15: lo que guarda TIC y se pidió a Bodega entra con la solicitud por documento.
        DB::statement('ALTER TABLE movimientos_consumible ALTER COLUMN tipo_documento TYPE varchar(20)');
    }

    public function down(): void
    {
        DB::statement("UPDATE movimientos_consumible SET tipo_documento = 'egreso_bodega' WHERE tipo_documento = 'solicitud_bodega'");
        DB::statement('ALTER TABLE movimientos_consumible ALTER COLUMN tipo_documento TYPE varchar(15)');
        Schema::table('movimientos_consumible', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitud_bodega_id');
        });
        Schema::dropIfExists('solicitud_bodega_items');
        Schema::dropIfExists('solicitudes_bodega');
        DB::table('categorias_ticket')->where('es_consumibles', true)->whereNotExists(
            fn ($q) => $q->select(DB::raw(1))->from('tickets')->whereColumn('tickets.categoria_id', 'categorias_ticket.id')
        )->delete();
        Schema::table('categorias_ticket', function (Blueprint $table) {
            $table->dropColumn('es_consumibles');
        });
    }

    /** La categoría «Consumibles», de Soporte Técnico [P16]; si TIC ya tiene una con ese nombre, solo se marca. */
    private function sembrarCategoria(): void
    {
        $existente = DB::table('categorias_ticket')->whereRaw('lower(nombre) = ?', ['consumibles'])->whereNull('deleted_at')->value('id');
        if ($existente) {
            DB::table('categorias_ticket')->where('id', $existente)->update(['es_consumibles' => true, 'es_hardware' => true]);

            return;
        }

        $tic = DB::table('unidades_administrativas')->where('codigo', config('helpdesk.unidad_tic_codigo'))->value('id');
        $soporte = $tic
            ? DB::table('unidades_administrativas')->where('unidad_padre_id', $tic)->where('nombre', 'Soporte Técnico')->value('id')
            : null;

        DB::table('categorias_ticket')->insert([
            'nombre'         => 'Consumibles',
            'es_hardware'    => true,
            'es_consumibles' => true,
            'descripcion'    => 'Tóner, tinta y repuestos: TIC los pide a Bodega con la solicitud de materiales',
            'unidad_tic_id'  => $soporte,
            'activa'         => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
};

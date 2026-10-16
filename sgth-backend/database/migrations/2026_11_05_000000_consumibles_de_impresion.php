<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los consumibles de impresión y los repuestos informáticos (fase 5.4a,
 * docs/diseno-subsistema-tic.md y docs/Diseno_Consumibles_TIC.docx).
 *
 * - Un catálogo único (tóner, tinta, cartuchos, cintas, unidades de imagen,
 *   kits y repuestos), con la compatibilidad por modelo de equipo [P1].
 * - Cada consumible dice si lo guarda TIC [P4]: entonces lleva saldo, que es
 *   la suma de sus movimientos (apertura, ingresos, entregas y ajustes), sin
 *   lotes [P2]. Lo que despacha Bodega solo registra sus entregas, que no
 *   tocan ningún saldo (`afecta_saldo`).
 * - Nada se borra: un movimiento mal hecho se anula con su motivo [P10].
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumibles', function (Blueprint $table) {
            $table->id();
            // El código con que llega de bodega; opcional y sin repetirse [P8].
            $table->string('codigo_bodega', 30)->nullable()->unique();
            $table->string('descripcion', 200);
            // toner, tinta, cartucho, cinta, unidad_imagen, kit_mantenimiento, otro, repuesto
            $table->string('tipo', 20);
            // negro, cian, magenta, amarillo, tricolor; null = no aplica
            $table->string('color', 10)->nullable();
            // unidad, botella, caja
            $table->string('presentacion', 10);
            $table->boolean('guarda_tic')->default(true);
            // Solo cuenta si lo guarda TIC, y solo se ve en el tablero [P6].
            $table->unsignedInteger('stock_minimo')->default(0);
            $table->boolean('activo')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['activo', 'tipo']);
        });

        Schema::create('consumible_modelos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumible_id')->constrained('consumibles')->cascadeOnDelete();
            $table->foreignId('marca_id')->constrained('marcas');
            $table->string('modelo', 100);
            // Sin mayúsculas ni espacios: «LaserJet M551» = «LASERJET  M551».
            $table->string('modelo_normalizado', 100);
            $table->timestamps();
            $table->unique(['consumible_id', 'marca_id', 'modelo_normalizado']);
            $table->index(['marca_id', 'modelo_normalizado']);
        });

        Schema::create('movimientos_consumible', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumible_id')->constrained('consumibles');
            // apertura, ingreso, entrega, ajuste
            $table->string('tipo', 10);
            // Con signo: suma la apertura y el ingreso, resta la entrega, el ajuste va de los dos lados.
            $table->integer('cantidad');
            // Lo que despacha Bodega no lleva saldo en TIC: su entrega no cuenta para ninguno.
            $table->boolean('afecta_saldo');
            $table->date('fecha');
            // La entrega: el equipo, quién retira y la unidad de ese momento.
            $table->foreignId('bien_informatico_id')->nullable()->constrained('bienes_informaticos');
            $table->foreignId('servidor_retira_id')->nullable()->constrained('servidores');
            $table->foreignId('unidad_administrativa_id')->nullable()->constrained('unidades_administrativas');
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            // El ingreso: con qué documento recibe TIC (egreso de bodega, acta o memorando).
            $table->string('tipo_documento', 15)->nullable();
            $table->string('numero_documento', 80)->nullable();
            $table->date('fecha_compra')->nullable();
            // El ajuste: daño, caducidad, diferencia en el conteo u otro.
            $table->string('motivo_ajuste', 20)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->foreignId('registrado_por')->constrained('users');
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users');
            $table->string('motivo_anulacion', 300)->nullable();
            $table->timestamps();
            $table->index(['consumible_id', 'fecha']);
            $table->index(['bien_informatico_id', 'fecha']);
            $table->index(['unidad_administrativa_id', 'fecha']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE movimientos_consumible ADD CONSTRAINT movimientos_consumible_signo_check CHECK (
                (tipo IN ('apertura', 'ingreso') AND cantidad > 0)
                OR (tipo = 'entrega' AND cantidad < 0)
                OR (tipo = 'ajuste' AND cantidad <> 0)
            )
            SQL);
        // Una sola apertura viva por consumible [P7].
        DB::statement("CREATE UNIQUE INDEX movimientos_consumible_apertura_unique ON movimientos_consumible (consumible_id) WHERE tipo = 'apertura' AND anulado_en IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_consumible');
        Schema::dropIfExists('consumible_modelos');
        Schema::dropIfExists('consumibles');
    }
};

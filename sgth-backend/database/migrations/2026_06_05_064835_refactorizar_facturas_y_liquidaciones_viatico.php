<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Categorías de facturas (configurable)
        Schema::create('categorias_factura', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('codigo', 50)->unique();
            $table->string('descripcion', 200)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // 2. Refactorizar facturas_viatico
        Schema::table('facturas_viatico', function (Blueprint $table) {
            // Eliminar concepto varchar libre
            $table->dropColumn('concepto');

            // Agregar FK a categoría
            $table->foreignId('categoria_factura_id')
                  ->after('liquidacion_viatico_id')
                  ->constrained('categorias_factura');

            // Fecha de la factura/comprobante
            $table->date('fecha_factura')
                  ->nullable()
                  ->after('categoria_factura_id');

            // Tipo de comprobante
            $table->enum('tipo_comprobante', [
                'factura', 'ticket', 'recibo', 'otro'
            ])->default('factura')
              ->after('fecha_factura');

            // numero_factura pasa a nullable (tickets no tienen)
            $table->string('numero_factura', 50)
                  ->nullable()
                  ->change();

            // Número de ticket alternativo
            $table->string('numero_ticket', 100)
                  ->nullable()
                  ->after('numero_factura');
        });

        // 3. Refactorizar liquidaciones_viatico
        Schema::table('liquidaciones_viatico', function (Blueprint $table) {
            // Eliminar campo JSON redundante
            if (Schema::hasColumn('liquidaciones_viatico', 'facturas')) {
                $table->dropColumn('facturas');
            }
            // Eliminar monto_justificado (= total_facturas)
            if (Schema::hasColumn('liquidaciones_viatico', 'monto_justificado')) {
                $table->dropColumn('monto_justificado');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_factura');
    }
};

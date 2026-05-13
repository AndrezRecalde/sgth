<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('origenes_bien', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_origen', 50); // compra_publica, donacion_nacional, donacion_internacional, comodato
            $table->string('identificador_documento', 100)->nullable(); // Opcional para donaciones
            $table->string('entidad_origen', 150);
            $table->date('fecha_adquisicion');
            $table->date('garantia_hasta')->nullable();
            $table->string('url_documento_pdf')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('origenes_bien'); }
};
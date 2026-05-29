<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('folios_permiso');
    }

    public function down(): void
    {
        Schema::create('folios_permiso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permiso_id')
                  ->constrained('permisos_servidor')
                  ->onDelete('cascade');
            $table->string('folio', 20)->unique();
            $table->string('qr_ruta')->nullable();
            $table->timestamps();
        });
    }
};

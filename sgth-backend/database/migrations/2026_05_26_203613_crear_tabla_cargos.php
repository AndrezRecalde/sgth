<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('denominacion_generica', 100)->nullable();
            $table->text('mision')->nullable();
            $table->enum('clasificacion_personal', [
                'empleado', 'contratado', 'obrero',
            ])->default('empleado');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};

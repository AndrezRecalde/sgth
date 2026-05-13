<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tecnicos_dtic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('area_dtic_id')->constrained('areas_dtic')->restrictOnDelete();
            $table->integer('nivel')->default(1); // 1: Mesa, 2: Especialista, 3: Proveedor
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('tecnicos_dtic'); }
};
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tipos_bien', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->integer('anios_vida_util')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('tipos_bien'); }
};
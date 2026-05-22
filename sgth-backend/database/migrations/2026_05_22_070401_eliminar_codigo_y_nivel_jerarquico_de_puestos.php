<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'nivel_jerarquico']);
        });
    }

    public function down(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            $table->string('codigo', 50)->nullable();
            $table->unsignedTinyInteger('nivel_jerarquico')->nullable();
        });
    }
};

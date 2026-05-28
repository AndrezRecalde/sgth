<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('regimen_laboral', 50)->nullable()->change();
            $table->unsignedBigInteger('unidad_administrativa_id')->nullable()->change();
            $table->unsignedBigInteger('puesto_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('regimen_laboral', 50)->nullable(false)->change();
            $table->unsignedBigInteger('unidad_administrativa_id')->nullable(false)->change();
            $table->unsignedBigInteger('puesto_id')->nullable(false)->change();
        });
    }
};

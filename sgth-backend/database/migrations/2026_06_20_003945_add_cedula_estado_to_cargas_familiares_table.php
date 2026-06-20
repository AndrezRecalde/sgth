<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargas_familiares', function (Blueprint $table) {
            $table->string('cedula', 10)->nullable()->unique()
                  ->after('servidor_id');
            $table->boolean('estado')->default(true)
                  ->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('cargas_familiares', function (Blueprint $table) {
            $table->dropColumn(['cedula', 'estado']);
        });
    }
};

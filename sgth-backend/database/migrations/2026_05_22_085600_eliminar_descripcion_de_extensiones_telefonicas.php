<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extensiones_telefonicas', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('extensiones_telefonicas', function (Blueprint $table) {
            $table->text('descripcion')->nullable();
        });
    }
};

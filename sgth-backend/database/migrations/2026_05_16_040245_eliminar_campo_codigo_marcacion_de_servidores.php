<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropIndex(['codigo_marcacion']);
            $table->dropColumn('codigo_marcacion');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('codigo_marcacion', 10)->nullable();
            $table->index('codigo_marcacion');
        });
    }
};

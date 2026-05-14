<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('categoria');
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_ticket')->restrictOnDelete();
        });
    }
    public function down(): void { 
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categoria_id');
            $table->string('categoria', 50)->nullable();
        });
    }
};
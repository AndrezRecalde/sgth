<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->string('folio', 20)->unique()->nullable()->after('id');
            $table->text('codigo_qr')->nullable()->after('folio');
            $table->foreignId('jefe_id')
                  ->nullable()->after('servidor_id')
                  ->constrained('servidores')->nullOnDelete();
            $table->enum('motivo', [
                'vacaciones_anuales',
                'permiso_cargo_vacaciones',
                'licencia_sin_goce',
                'matrimonio',
                'capacitacion',
                'enfermedad',
                'maternidad',
                'paternidad',
                'estudios_sin_remuneracion',
                'calamidad_domestica',
                'licencia_con_goce',
            ])->after('tipo_dias')->nullable();
            $table->date('fecha_retorno')->nullable()->after('fecha_fin');
            $table->date('fecha_emision')->nullable()->after('fecha_retorno');
            $table->foreignId('creado_por')
                  ->nullable()
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropForeign(['jefe_id']);
            $table->dropForeign(['creado_por']);
            $table->dropUnique(['folio']);
            $table->dropColumn([
                'folio','codigo_qr','jefe_id','motivo',
                'fecha_retorno','fecha_emision','creado_por',
            ]);
        });
    }
};

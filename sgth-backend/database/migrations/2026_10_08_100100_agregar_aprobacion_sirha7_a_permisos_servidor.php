<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La aprobación de un permiso en Sirha7, el biométrico.
 *
 * En el permiso queda cómo se registró (el tipo de LeaveClass que eligió quien
 * aprobó), quién lo aprobó, cuándo, a qué USERID del biométrico fue y qué días
 * se omitieron. Las filas que escribió el procedimiento, una por día, van en
 * su propia tabla con su ID de Sirha7: para retirarlas hay que decirle al
 * procedimiento cuántas son, y si en Sirha7 aparece otra cantidad no se borra
 * nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->integer('sirha7_leave_id')->nullable()->after('motivo_reversion');
            $table->string('sirha7_leave_nombre', 200)->nullable()->after('sirha7_leave_id');
            $table->integer('sirha7_userid')->nullable()->after('sirha7_leave_nombre');
            $table->foreignId('sirha7_aprobado_por')
                ->nullable()
                ->after('sirha7_userid')
                ->constrained('users');
            $table->timestamp('sirha7_aprobado_en')->nullable()->after('sirha7_aprobado_por');
            $table->json('sirha7_dias_omitidos')->nullable()->after('sirha7_aprobado_en');
        });

        Schema::create('permiso_sirha7_filas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permiso_servidor_id')
                ->constrained('permisos_servidor')
                ->cascadeOnDelete();
            // El ID de dbo.USER_SPEDAY en Sirha7.
            $table->integer('sirha7_id');
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->timestamps();

            $table->unique(['permiso_servidor_id', 'sirha7_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permiso_sirha7_filas');

        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->dropForeign(['sirha7_aprobado_por']);
            $table->dropColumn([
                'sirha7_leave_id', 'sirha7_leave_nombre', 'sirha7_userid',
                'sirha7_aprobado_por', 'sirha7_aprobado_en', 'sirha7_dias_omitidos',
            ]);
        });
    }
};

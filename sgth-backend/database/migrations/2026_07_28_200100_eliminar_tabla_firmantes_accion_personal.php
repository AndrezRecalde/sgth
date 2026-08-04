<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se retira la designación manual de firmantes: ahora se derivan del
 * organigrama (ver FirmanteAccionPersonalService). Mantener las dos vías
 * significaba dos fuentes de verdad que podían contradecirse — y la manual es
 * la que se desactualiza cuando cambian las autoridades.
 *
 * No se pierde auditoría: quién firmó cada acción vive sellado en las columnas
 * firmante_* de 'movimientos_personal', no en esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('firmantes_accion_personal');
    }

    public function down(): void
    {
        Schema::create('firmantes_accion_personal', function (Blueprint $table) {
            $table->id();
            $table->string('rol_firma', 40);
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['rol_firma', 'vigente_desde']);
        });
    }
};

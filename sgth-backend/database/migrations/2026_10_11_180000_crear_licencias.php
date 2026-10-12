<?php

use App\Enums\CausaLicencia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Las licencias con remuneración, fuera de las vacaciones (diseño de Acciones
 * de Personal, 4.4 y 9.4; TH N12; fase 5.1). Hasta aquí eran «motivos» de la
 * solicitud de vacaciones, sin tope ni respaldo, y nunca llegaban a Sirha7.
 *
 * - `licencias`: la causa, el caso que cambia el tope, las fechas, el PDF del
 *   respaldo, y su aprobación y su registro en Sirha7, como los certificados
 *   médicos (2026-10-08).
 * - `licencia_sirha7_filas`: lo escrito en dbo.USER_SPEDAY, una fila por día y
 *   con su referencia: la licencia de más de 31 días va en tramos, cada uno
 *   con la suya.
 * - `gestionar-licencias`: registrarlas y anularlas. Lo tienen admin-uath y
 *   asistente-uath, si ya existen; en una base nueva lo da el seeder.
 */
return new class extends Migration
{
    private const PERMISO = 'gestionar-licencias';

    private const ROLES = ['admin-uath', 'asistente-uath'];

    public function up(): void
    {
        Schema::create('licencias', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->string('causa', 40);
            $table->string('alcance', 40)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedSmallInteger('dias');
            $table->text('observacion')->nullable();
            $table->string('documento_ruta');
            $table->string('documento_nombre');
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable();
            // 'sgth' si la escribió el SGTH en Sirha7; 'manual' si TH ya la cargó.
            $table->string('registro_sirha7', 10)->nullable();
            $table->string('nota_aprobacion', 500)->nullable();
            $table->integer('sirha7_leave_id')->nullable();
            $table->string('sirha7_leave_nombre', 200)->nullable();
            $table->integer('sirha7_userid')->nullable();
            $table->json('sirha7_dias_omitidos')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['servidor_id', 'fecha_inicio']);
        });

        $causas = implode(', ', array_map(fn (string $c) => "'{$c}'", CausaLicencia::valores()));

        DB::statement("ALTER TABLE licencias ADD CONSTRAINT licencias_causa_check CHECK (causa IN ({$causas}))");
        DB::statement('ALTER TABLE licencias ADD CONSTRAINT licencias_fechas_check
            CHECK (fecha_fin >= fecha_inicio AND dias > 0)');
        DB::statement("ALTER TABLE licencias ADD CONSTRAINT licencias_registro_check
            CHECK (registro_sirha7 IS NULL OR registro_sirha7 IN ('sgth', 'manual'))");

        Schema::create('licencia_sirha7_filas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('licencia_id')->constrained('licencias')->cascadeOnDelete();
            // El ID de dbo.USER_SPEDAY en Sirha7, y la referencia de su tramo.
            $table->integer('sirha7_id');
            $table->string('referencia', 60);
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->timestamps();

            $table->unique(['licencia_id', 'sirha7_id']);
        });

        $permiso = Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'sanctum']);

        foreach (self::ROLES as $nombre) {
            Role::where('name', $nombre)->where('guard_name', 'sanctum')->first()?->givePermissionTo($permiso);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('licencia_sirha7_filas');
        Schema::dropIfExists('licencias');

        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

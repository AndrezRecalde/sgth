<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El préstamo de equipos (docs/diseno-subsistema-tic.md, 4.6; fase 2.1).
 *
 * TIC presta por horas o días los equipos marcados como prestables
 * (proyectores, parlantes, auriculares). Un préstamo no es una custodia: tiene
 * horas de entrega y devolución previstas, y otras dos reales.
 *
 * - `prestamos_equipo`: la solicitud y su trámite, con folio PRE-AAAA-00001.
 * - `prestamo_items`: los equipos concretos que eligió el servidor (D13), con
 *   los accesorios y la condición a la entrega y a la devolución.
 * - `historial_estados_prestamo`: cada cambio de estado, quién y cuándo.
 * - `reglas_prestamo`: una fila con las reglas que TIC configura (D16).
 *
 * **Un equipo no se compromete dos veces en el mismo horario.** Lo garantiza
 * una restricción de exclusión sobre el ítem, solo mientras el préstamo está
 * reservado o en curso (`bloquea`): dos solicitudes del mismo proyector a la
 * misma hora pueden existir, pero solo una se aprueba. Necesita `btree_gist`
 * para comparar el id del equipo con `=` dentro de un índice GiST; es una
 * extensión de confianza desde PostgreSQL 13, así que la crea el dueño de la
 * base sin superusuario.
 *
 * Permiso nuevo `gestionar-prestamos` para el técnico, el jefe de TIC y
 * admin-ti. El Portal (solicitar, ver la disponibilidad, «Mis préstamos») va
 * con `acceso-autoservicio`, que ya tienen todos: un permiso base nuevo
 * obligaría a tocar las migraciones de cada rol sin agregar control.
 */
return new class extends Migration
{
    private const PERMISO = 'gestionar-prestamos';

    private const ROLES = ['tecnico-dtic', 'jefe-tic', 'admin-ti'];

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('reglas_prestamo', function (Blueprint $table) {
            $table->id();
            // D16: hasta 5 días hábiles, sin préstamo nuevo con uno vencido y
            // una hora de margen para retirar.
            $table->unsignedSmallInteger('plazo_maximo_dias_habiles')->default(5);
            $table->unsignedSmallInteger('anticipacion_minima_minutos')->default(0);
            $table->time('hora_apertura')->default('08:00');
            $table->time('hora_cierre')->default('17:00');
            $table->boolean('presta_fines_de_semana')->default(false);
            $table->boolean('bloquea_con_vencido')->default(true);
            $table->unsignedSmallInteger('margen_retiro_minutos')->default(60);
            $table->foreignId('actualizado_por')->nullable()->constrained('users');
            $table->timestamps();
        });
        DB::table('reglas_prestamo')->insert(['created_at' => now(), 'updated_at' => now()]);

        Schema::create('prestamos_equipo', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->foreignId('servidor_id')->constrained('servidores');
            // La unidad del servidor al pedir: si cambia de unidad, el préstamo
            // sigue contando para la de entonces.
            $table->foreignId('unidad_administrativa_id')->nullable()->constrained('unidades_administrativas');
            $table->string('estado', 20)->default('solicitado');
            $table->text('motivo');
            $table->string('lugar_uso', 200);
            $table->boolean('fuera_del_edificio')->default(false);
            $table->timestamp('inicio_previsto');
            $table->timestamp('fin_previsto');
            // Registrado y entregado en el mostrador, sin solicitud previa.
            $table->boolean('directo')->default(false);
            $table->foreignId('registrado_por')->nullable()->constrained('users');
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->timestamp('aprobado_en')->nullable();
            $table->foreignId('rechazado_por')->nullable()->constrained('users');
            $table->timestamp('rechazado_en')->nullable();
            $table->string('motivo_rechazo', 500)->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users');
            $table->timestamp('cancelado_en')->nullable();
            $table->string('motivo_cancelacion', 500)->nullable();
            $table->foreignId('entregado_por')->nullable()->constrained('users');
            $table->timestamp('entregado_en')->nullable();
            $table->foreignId('recibido_por')->nullable()->constrained('users');
            $table->timestamp('devuelto_en')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['estado', 'inicio_previsto']);
            $table->index(['servidor_id', 'estado']);
        });
        DB::statement('ALTER TABLE prestamos_equipo ADD CONSTRAINT prestamos_equipo_horario_check CHECK (fin_previsto > inicio_previsto)');

        Schema::create('prestamo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_equipo_id')->constrained('prestamos_equipo')->cascadeOnDelete();
            $table->foreignId('bien_informatico_id')->constrained('bienes_informaticos')->restrictOnDelete();
            $table->timestamp('inicio');
            $table->timestamp('fin');
            // Verdadero mientras el préstamo está reservado o en curso: lo
            // mueve el servicio con cada cambio de estado.
            $table->boolean('bloquea')->default(false);
            $table->string('accesorios', 300)->nullable();
            $table->string('condicion_entrega', 20)->nullable();
            $table->string('condicion_devolucion', 20)->nullable();
            $table->text('novedad')->nullable();
            $table->timestamps();

            $table->unique(['prestamo_equipo_id', 'bien_informatico_id']);
            $table->index(['bien_informatico_id', 'bloquea']);
        });
        DB::statement("ALTER TABLE prestamo_items ADD COLUMN periodo tsrange GENERATED ALWAYS AS (tsrange(inicio, fin, '[)')) STORED");
        DB::statement('ALTER TABLE prestamo_items ADD CONSTRAINT prestamo_items_sin_solapes EXCLUDE USING gist (bien_informatico_id WITH =, periodo WITH &&) WHERE (bloquea)');

        Schema::create('historial_estados_prestamo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_equipo_id')->constrained('prestamos_equipo')->cascadeOnDelete();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('detalle', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['prestamo_equipo_id', 'id']);
        });

        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'sanctum']);
        foreach (self::ROLES as $rol) {
            Role::where('name', $rol)->where('guard_name', 'sanctum')->first()?->givePermissionTo(self::PERMISO);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_estados_prestamo');
        Schema::dropIfExists('prestamo_items');
        Schema::dropIfExists('prestamos_equipo');
        Schema::dropIfExists('reglas_prestamo');

        // La extensión se queda: puede usarla otra tabla y no estorba.
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El inventario de equipos con lo que trae el Excel de TIC
 * (docs/diseno-subsistema-tic.md, 2.1 y 4.2; fase 1.1).
 *
 * - Catálogos nuevos: ubicaciones (sede y piso), subtipos de bien («Todo en
 *   uno», «CPU»…) y contratos de vigencia tecnológica con su cronograma de
 *   mantenimientos.
 * - El equipo gana el código anterior, el subtipo, la ubicación, la unidad
 *   (para el que no tiene custodio), la fecha de compra, los datos de red, el
 *   equipo principal del que es componente, el contrato de vigencia, la marca
 *   de prestable y las observaciones.
 * - La serie y el origen pasan a ser opcionales: el Excel trae «S/N» y no
 *   trae el origen de lo histórico.
 * - Los únicos (código, serie, nombres de catálogo) dejan de contar las filas
 *   borradas: borrar una ficha registrada por error impedía volver a
 *   registrarla bien. Los nombres de catálogo se comparan sin mayúsculas:
 *   «DELL» y «Dell» son la misma marca.
 * - Los estados cambian (`EstadoBien`): `activo` pasa a `en_uso` si el equipo
 *   tiene custodio y a `disponible` si no; `robado` y `perdido`, a
 *   `extraviado`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('sede', 100);
            $table->string('piso', 30)->nullable();
            $table->string('descripcion', 200)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement("CREATE UNIQUE INDEX ubicaciones_sede_piso_unique ON ubicaciones (lower(sede), lower(coalesce(piso, ''))) WHERE deleted_at IS NULL");

        Schema::create('subtipos_bien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_bien_id')->constrained('tipos_bien')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('CREATE UNIQUE INDEX subtipos_bien_tipo_nombre_unique ON subtipos_bien (tipo_bien_id, lower(nombre)) WHERE deleted_at IS NULL');

        Schema::create('contratos_vigencia', function (Blueprint $table) {
            $table->id();
            $table->string('proceso', 100);
            $table->string('proveedor', 200)->nullable();
            $table->text('objeto')->nullable();
            $table->foreignId('administrador_id')->nullable()->constrained('servidores')->nullOnDelete();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('CREATE UNIQUE INDEX contratos_vigencia_proceso_unique ON contratos_vigencia (lower(proceso)) WHERE deleted_at IS NULL');

        Schema::create('cronogramas_vigencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_vigencia_id')->constrained('contratos_vigencia')->cascadeOnDelete();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->string('descripcion', 200)->nullable();
            $table->timestamps();
        });

        foreach (['tipos_bien', 'marcas'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropUnique("{$tabla}_nombre_unique"));
            DB::statement("CREATE UNIQUE INDEX {$tabla}_nombre_unique ON {$tabla} (lower(nombre)) WHERE deleted_at IS NULL");
        }

        Schema::table('bienes_informaticos', function (Blueprint $table) {
            $table->dropUnique('bienes_informaticos_codigo_qr_unique');
            $table->dropUnique('bienes_informaticos_codigo_institucional_unique');
            $table->dropUnique('bienes_informaticos_numero_serie_unique');

            $table->string('numero_serie', 100)->nullable()->change();
            $table->unsignedBigInteger('origen_bien_id')->nullable()->change();
            $table->string('estado_operativo', 50)->default('disponible')->change();

            $table->string('codigo_anterior', 50)->nullable()->after('codigo_institucional');
            $table->foreignId('subtipo_bien_id')->nullable()->after('tipo_bien_id')
                ->constrained('subtipos_bien')->restrictOnDelete();
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->restrictOnDelete();
            $table->foreignId('unidad_administrativa_id')->nullable()
                ->constrained('unidades_administrativas')->nullOnDelete();
            $table->date('fecha_compra')->nullable();
            $table->string('nombre_red', 100)->nullable();
            $table->string('usuario_dominio', 100)->nullable();
            $table->string('direccion_ip', 45)->nullable();
            $table->foreignId('bien_padre_id')->nullable()->constrained('bienes_informaticos')->nullOnDelete();
            $table->foreignId('contrato_vigencia_id')->nullable()->constrained('contratos_vigencia')->nullOnDelete();
            $table->boolean('prestable')->default(false);
            $table->text('observaciones')->nullable();

            $table->index('codigo_anterior');
            $table->index('estado_operativo');
        });

        foreach (['codigo_qr', 'codigo_institucional', 'numero_serie'] as $columna) {
            DB::statement("CREATE UNIQUE INDEX bienes_informaticos_{$columna}_unique ON bienes_informaticos ({$columna}) WHERE deleted_at IS NULL AND {$columna} IS NOT NULL");
        }

        DB::statement("
            UPDATE bienes_informaticos b SET estado_operativo = CASE
                WHEN EXISTS (
                    SELECT 1 FROM asignaciones_bien a
                    WHERE a.bien_informatico_id = b.id AND a.estado = 'activa' AND a.deleted_at IS NULL
                ) THEN 'en_uso'
                ELSE 'disponible'
            END
            WHERE estado_operativo = 'activo'
        ");
        DB::table('bienes_informaticos')
            ->whereIn('estado_operativo', ['robado', 'perdido'])
            ->update(['estado_operativo' => 'extraviado']);
    }

    public function down(): void
    {
        DB::table('bienes_informaticos')
            ->whereIn('estado_operativo', ['en_uso', 'disponible', 'en_garantia'])
            ->update(['estado_operativo' => 'activo']);
        DB::table('bienes_informaticos')
            ->where('estado_operativo', 'extraviado')
            ->update(['estado_operativo' => 'perdido']);

        foreach (['codigo_qr', 'codigo_institucional', 'numero_serie'] as $columna) {
            DB::statement("DROP INDEX IF EXISTS bienes_informaticos_{$columna}_unique");
        }

        Schema::table('bienes_informaticos', function (Blueprint $table) {
            $table->dropForeign(['subtipo_bien_id']);
            $table->dropForeign(['ubicacion_id']);
            $table->dropForeign(['unidad_administrativa_id']);
            $table->dropForeign(['bien_padre_id']);
            $table->dropForeign(['contrato_vigencia_id']);
            $table->dropIndex(['codigo_anterior']);
            $table->dropIndex(['estado_operativo']);
            $table->dropColumn([
                'codigo_anterior', 'subtipo_bien_id', 'ubicacion_id', 'unidad_administrativa_id',
                'fecha_compra', 'nombre_red', 'usuario_dominio', 'direccion_ip', 'bien_padre_id',
                'contrato_vigencia_id', 'prestable', 'observaciones',
            ]);
            $table->string('estado_operativo', 50)->default('activo')->change();
            $table->unique('codigo_qr');
            $table->unique('codigo_institucional');
            $table->unique('numero_serie');
        });

        foreach (['tipos_bien', 'marcas'] as $tabla) {
            DB::statement("DROP INDEX IF EXISTS {$tabla}_nombre_unique");
            Schema::table($tabla, fn (Blueprint $table) => $table->unique('nombre'));
        }

        Schema::dropIfExists('cronogramas_vigencia');
        Schema::dropIfExists('contratos_vigencia');
        Schema::dropIfExists('subtipos_bien');
        Schema::dropIfExists('ubicaciones');
    }
};

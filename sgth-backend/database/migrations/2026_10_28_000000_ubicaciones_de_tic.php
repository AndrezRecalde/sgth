<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las ubicaciones de los equipos (decidido el 2026-10-13 con TIC): son un dato
 * del equipo, no de la unidad, porque una nueva administración puede cambiar
 * de piso a las unidades. Las sedes son el Edificio Central (planta baja y
 * pisos 1 a 6), el Dispensario Médico y San Mateo; UNAMY ya no existe.
 *
 * - `ubicacion_por_unidad`: dónde suele estar cada unidad, solo para proponer
 *   la ubicación al registrar o entregar un equipo y para la mudanza en lote.
 * - `movimientos_ubicacion_bien`: cada cambio de ubicación de un equipo, para
 *   su historial.
 * - El catálogo se normaliza: la carga del Excel creaba «Edificio principal»
 *   con pisos «PB», «1», «2»…
 */
return new class extends Migration
{
    private const CENTRAL = 'Edificio Central';

    public function up(): void
    {
        Schema::create('ubicacion_por_unidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_administrativa_id')->unique()->constrained('unidades_administrativas')->cascadeOnDelete();
            $table->foreignId('ubicacion_id')->constrained('ubicaciones')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('movimientos_ubicacion_bien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_informatico_id')->constrained('bienes_informaticos')->cascadeOnDelete();
            $table->foreignId('ubicacion_anterior_id')->nullable()->constrained('ubicaciones');
            $table->foreignId('ubicacion_nueva_id')->nullable()->constrained('ubicaciones');
            // edicion, entrega, traspaso, mudanza
            $table->string('motivo', 20);
            $table->string('detalle', 300)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['bien_informatico_id', 'created_at']);
        });

        $this->normalizarCatalogo();
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_ubicacion_bien');
        Schema::dropIfExists('ubicacion_por_unidad');
        // El catálogo normalizado se queda: los nombres nuevos no rompen nada.
    }

    private function normalizarCatalogo(): void
    {
        $vivas = fn () => DB::table('ubicaciones')->whereNull('deleted_at');

        // «Edificio principal» → «Edificio Central»; «PB» → «Planta baja»; «3» → «Piso 3».
        foreach ($vivas()->whereRaw('lower(sede) = ?', ['edificio principal'])->get() as $u) {
            $piso = match (true) {
                $u->piso === null => null,
                mb_strtolower($u->piso) === 'pb' => 'Planta baja',
                (bool) preg_match('/^\d+$/', $u->piso) => "Piso {$u->piso}",
                default => $u->piso,
            };
            $this->renombrar($u->id, self::CENTRAL, $piso);
        }

        // UNAMY ya no existe: se retira si ningún equipo está allí.
        foreach ($vivas()->whereRaw("lower(sede) in ('unamy', 'unamydesc')")->get() as $u) {
            if (! DB::table('bienes_informaticos')->where('ubicacion_id', $u->id)->exists()) {
                DB::table('ubicaciones')->where('id', $u->id)->update(['deleted_at' => now()]);
            }
        }

        // Las del catálogo, si faltan.
        $catalogo = [[self::CENTRAL, null], [self::CENTRAL, 'Planta baja']];
        foreach (range(1, 6) as $n) {
            $catalogo[] = [self::CENTRAL, "Piso {$n}"];
        }
        $catalogo[] = ['Dispensario Médico', null];
        $catalogo[] = ['San Mateo', null];

        foreach ($catalogo as [$sede, $piso]) {
            if (! $this->existe($sede, $piso)) {
                DB::table('ubicaciones')->insert(['sede' => $sede, 'piso' => $piso, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /** Si ya hay una con ese nombre, sus equipos pasan a ella y esta se retira. */
    private function renombrar(int $id, string $sede, ?string $piso): void
    {
        $destino = $this->existe($sede, $piso, $id);
        if ($destino) {
            DB::table('bienes_informaticos')->where('ubicacion_id', $id)->update(['ubicacion_id' => $destino]);
            DB::table('ubicaciones')->where('id', $id)->update(['deleted_at' => now()]);

            return;
        }

        DB::table('ubicaciones')->where('id', $id)->update(['sede' => $sede, 'piso' => $piso, 'updated_at' => now()]);
    }

    private function existe(string $sede, ?string $piso, ?int $salvo = null): ?int
    {
        return DB::table('ubicaciones')->whereNull('deleted_at')
            ->whereRaw('lower(sede) = ?', [mb_strtolower($sede)])
            ->whereRaw("lower(coalesce(piso, '')) = ?", [mb_strtolower($piso ?? '')])
            ->when($salvo, fn ($q) => $q->where('id', '<>', $salvo))
            ->value('id');
    }
};

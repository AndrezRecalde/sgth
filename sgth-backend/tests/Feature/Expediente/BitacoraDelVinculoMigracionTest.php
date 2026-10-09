<?php

namespace Tests\Feature\Expediente;

use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| La fase 1.2 saca la bitácora de `movimientos_personal`. Es una migración de
| datos sobre filas que solo existen en producción, así que la prueba las fabrica
| con el formato de antes: deshace la migración, inserta como lo hacía el código
| viejo y la vuelve a correr.
|
| Las filas van con DB::table() y no con el modelo: los tipos que salen ya no
| existen en el enum, y el cast reventaría al leerlas.
*/
beforeEach(function () {
    $this->user = User::factory()->create();

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-01', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1111111111', 'nombre' => 'Titular', 'apellido' => 'Bitacora',
        'regimen_laboral' => 'losep',
    ]);

    $this->contrato = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'fecha_inicio' => '2025-01-06',
        'estado' => 'vigente',
    ]);

    $this->migracion = require database_path('migrations/2026_10_09_110000_crear_bitacora_del_vinculo.php');
    $this->migracion->down();

    $this->fila = fn (array $datos): int => DB::table('movimientos_personal')->insertGetId([
        'servidor_id' => $this->servidor->id,
        'autorizado_por' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
        ...$datos,
    ]);

    // Una novedad en borrador, como las dejaba el código anterior al 2026-08-04.
    $this->novedad = ($this->fila)([
        'tipo_movimiento' => 'novedad_contrato',
        'estado' => 'borrador',
        'categoria' => 'accion_de_personal',
        'descripcion' => 'Sincronización de vínculo por contrato: Nombramiento Permanente.',
        'fecha_efectiva' => '2025-01-06',
        'unidad_destino_id' => $this->unidad->id,
        'puesto_destino_id' => $this->puesto->id,
    ]);

    $this->cambioPuesto = ($this->fila)([
        'tipo_movimiento' => 'cambio_puesto',
        'estado' => 'registrada',
        'descripcion' => 'Cambio de puesto anterior al catálogo',
        'fecha_efectiva' => '2025-06-01',
    ]);

    // La acción de la subrogación: tiene correlativo y categoría, y se queda.
    $this->acto = ($this->fila)([
        'tipo_movimiento' => 'subrogacion',
        'clase' => 'subrogacion',
        'estado' => 'registrada',
        'categoria' => 'accion_de_personal',
        'codigo_registro' => 'AP-2026-0001',
        'descripcion' => 'Subrogación del puesto de la Dirección',
        'fecha_efectiva' => '2026-09-01',
    ]);

    $this->subrogacion = DB::table('subrogaciones')->insertGetId([
        'tipo' => 'subrogacion',
        'servidor_subrogante_id' => $this->servidor->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id' => $this->puesto->id,
        'fecha_inicio' => '2026-09-01',
        'fecha_fin' => '2026-12-31',
        'motivo' => 'vacaciones',
        'estado' => 'finalizada',
        'movimiento_personal_id' => $this->acto,
        'registrado_por' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Su constancia: mismo tipo, sin correlativo ni categoría.
    $this->constancia = ($this->fila)([
        'tipo_movimiento' => 'subrogacion',
        'clase' => 'subrogacion',
        'estado' => 'registrada',
        'descripcion' => 'Finalización anticipada de Subrogación: terminó antes del 31/12/2026 previsto.',
        'fecha_efectiva' => '2026-10-01',
    ]);

    DB::table('configuracion_reporte_movimiento')->updateOrInsert(
        ['tipo_movimiento' => 'egreso'],
        ['reportable_siith' => false, 'reportable_sut' => false, 'created_at' => now(), 'updated_at' => now()],
    );
});

test('la bitácora sale de las acciones de personal y la acción de verdad se queda', function () {
    $this->migracion->up();

    expect(DB::table('movimientos_personal')->where('servidor_id', $this->servidor->id)->pluck('id')->all())
        ->toBe([$this->acto])
        ->and(DB::table('eventos_vinculo')->count())->toBe(3)
        ->and(DB::table('configuracion_reporte_movimiento')->where('tipo_movimiento', 'egreso')->exists())
        ->toBeFalse();
});

test('la novedad encuentra su contrato y conserva lo que era', function () {
    $this->migracion->up();

    $evento = DB::table('eventos_vinculo')->where('tipo', 'contrato_registrado')->sole();
    $datos = json_decode($evento->datos, true);

    expect($evento->contrato_servidor_id)->toBe($this->contrato->id)
        ->and($evento->fecha)->toBe('2025-01-06')
        ->and($evento->registrado_por)->toBe($this->user->id)
        ->and($datos['movimiento_original_id'])->toBe($this->novedad)
        ->and($datos['estado_original'])->toBe('borrador')
        ->and($datos['categoria'])->toBe('accion_de_personal')
        ->and($datos['puesto_destino_id'])->toBe($this->puesto->id);
});

test('la constancia de la subrogación encuentra su subrogación y su acto', function () {
    $this->migracion->up();

    $evento = DB::table('eventos_vinculo')->where('tipo', 'subrogacion_finalizada')->sole();

    expect($evento->subrogacion_id)->toBe($this->subrogacion)
        ->and($evento->movimiento_personal_id)->toBe($this->acto)
        ->and($evento->contrato_servidor_id)->toBeNull();
});

test('los tipos genéricos anteriores conservan su nombre', function () {
    $this->migracion->up();

    expect(DB::table('eventos_vinculo')->where('tipo', 'cambio_puesto')->value('descripcion'))
        ->toBe('Cambio de puesto anterior al catálogo');
});

test('después, la tabla de acciones ya no admite un tipo de bitácora', function () {
    $this->migracion->up();

    expect(fn () => ($this->fila)([
        'tipo_movimiento' => 'novedad_contrato',
        'descripcion' => 'Ya no cabe',
        'fecha_efectiva' => '2026-10-09',
    ]))->toThrow(QueryException::class);
});

test('deshacerla devuelve cada fila a su tipo', function () {
    $this->migracion->up();
    $this->migracion->down();

    $tipos = DB::table('movimientos_personal')
        ->where('servidor_id', $this->servidor->id)
        ->orderBy('id')
        ->get(['tipo_movimiento', 'estado', 'categoria', 'codigo_registro']);

    expect($tipos->pluck('tipo_movimiento')->all())
        ->toBe(['subrogacion', 'novedad_contrato', 'cambio_puesto', 'subrogacion'])
        ->and($tipos[1]->estado)->toBe('borrador')
        ->and($tipos[1]->categoria)->toBe('accion_de_personal')
        // La constancia vuelve sin categoría ni correlativo: así se la reconoce.
        ->and($tipos[3]->categoria)->toBeNull()
        ->and($tipos[3]->codigo_registro)->toBeNull()
        ->and(\Illuminate\Support\Facades\Schema::hasTable('eventos_vinculo'))->toBeFalse();

    // Y volver a subirla da el mismo resultado: la migración es repetible.
    $this->migracion->up();

    expect(DB::table('eventos_vinculo')->count())->toBe(3);
});

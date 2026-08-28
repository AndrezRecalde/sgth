<?php

namespace Tests\Feature\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * `Puesto::rmu` es un accesor —sale del grupo ocupacional—, no una columna, y
 * por eso `toArray()` no lo incluye por su cuenta: los recursos lo agregan a
 * mano. De él depende que la remuneración LOSEP se herede de la escala en vez
 * de teclearse, así que si deja de viajar el formulario de cierre de vínculo
 * se queda vacío y editable sin que nada falle a la vista.
 */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->grupo = GrupoOcupacional::create([
        'grado_codigo'          => 'SP7',
        'grado_numerico'        => 7,
        'grupo'                 => 'Servidor Público 7',
        'denominacion_generica' => 'Servidor Público 7',
        'rmu'                   => 1676.00,
        'regimen'               => 'losep',
        'activo'                => true,
    ]);

    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'grupo_ocupacional_id'     => $this->grupo->id,
        'plazas'                   => 5,
    ]);

    $this->servidor = Servidor::create([
        'cedula'                   => '0801234567',
        'nombre'                   => 'Analista',
        'apellido'                 => 'DePrueba',
        'regimen_laboral'          => 'losep',
        'puesto_id'                => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);
});

test('el expediente del servidor trae la R.M.U. de la escala del puesto', function () {
    $puesto = $this->getJson("/api/v1/expediente/servidores/{$this->servidor->id}")
        ->assertOk()
        ->json('datos.puesto');

    expect($puesto['rmu'])->toEqual(1676.00);
});

test('el detalle de la acción de personal trae la R.M.U. del puesto de destino', function () {
    $movimiento = MovimientoPersonal::create([
        'servidor_id'       => $this->servidor->id,
        // 'comision_servicios' es neutro: no exige contrato vigente ni
        // arrastra efectos económicos que aquí no vienen al caso.
        'tipo_movimiento'   => 'comision_servicios',
        'categoria'         => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'            => EstadoAccionPersonal::BORRADOR,
        'descripcion'       => 'Acción de prueba',
        'fecha_efectiva'    => now()->toDateString(),
        'puesto_destino_id' => $this->puesto->id,
        'autorizado_por'    => $this->user->id,
    ]);

    $destino = $this->getJson("/api/v1/expediente/movimientos/{$movimiento->id}")
        ->assertOk()
        ->json('datos.puesto_destino');

    expect($destino['rmu'])->toEqual(1676.00);
});

test('un puesto sin grupo ocupacional responde R.M.U. nula, no un error', function () {
    // Es el caso que deja el campo abierto en el formulario: sin escala
    // asignada no hay nada que heredar, y bloquearlo dejaría la acción
    // imposible de completar.
    $sinEscala = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'plazas'                   => 1,
    ]);

    $this->servidor->update(['puesto_id' => $sinEscala->id]);

    $puesto = $this->getJson("/api/v1/expediente/servidores/{$this->servidor->id}")
        ->assertOk()
        ->json('datos.puesto');

    expect($puesto)->toHaveKey('rmu')
        ->and($puesto['rmu'])->toBeNull();
});

<?php

/*
| El transporte de un tramo: el tipo siempre, la empresa solo si el tipo las
| tiene.
|
| El tramo guardaba solo la empresa. Un vehículo institucional no tiene, y no
| había forma de registrarlo: el formulario marcaba error y el backend lo
| rechazaba (2026-09-18).
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\AutorizacionVuelo;
use App\Models\Viatico\CatalogoTransporte;
use App\Models\Viatico\EmpresaTransporte;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['codigo' => 'GTRA']);
    $titular = Servidor::create([
        'cedula'                   => '0800009001',
        'nombre'                   => 'Tito',
        'apellido'                 => 'Tramo',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);
    $this->usuario = User::factory()->create(['servidor_id' => $titular->id]);
    $this->usuario->assignRole('servidor');

    $this->viatico = Viatico::create([
        'servidor_id'        => $titular->id,
        'zona'               => 'fuera_provincia',
        'datetime_salida'    => '2026-10-05 08:00:00',
        'datetime_llegada'   => '2026-10-07 18:00:00',
        'noches'             => 2,
        'justificacion'      => 'Supervisión de obras viales',
        'estado'             => EstadoViatico::SOLICITADO,
        'monto_calculado'    => 160,
        'monto_anticipo'     => 0,
        'modalidad_anticipo' => 'total',
    ]);

    $tipo = fn (string $codigo, string $vehiculo, bool $autoriza = false) => CatalogoTransporte::create([
        'nombre' => ucfirst($codigo), 'codigo' => $codigo, 'tipo_vehiculo' => $vehiculo,
        'requiere_autorizacion' => $autoriza, 'activo' => true,
    ]);

    $this->bus = $tipo('bus', 'terrestre');
    $this->avion = $tipo('avion', 'aereo', true);
    $this->institucional = $tipo('vehiculo_institucional', 'terrestre');

    $this->cooperativa = EmpresaTransporte::create(['catalogo_transporte_id' => $this->bus->id, 'nombre' => 'Trans Esmeraldas', 'codigo' => 'TE']);
    $this->aerolinea = EmpresaTransporte::create(['catalogo_transporte_id' => $this->avion->id, 'nombre' => 'LATAM', 'codigo' => 'LA']);

    $this->url = "/api/v1/viaticos/{$this->viatico->id}/tramos";
    $this->tramo = fn (array $transporte) => [
        'origen_tipo' => 'nacional', 'origen_ciudad' => 'Esmeraldas',
        'destino_tipo' => 'nacional', 'destino_ciudad' => 'Quito',
        'datetime_salida' => '2026-10-05 08:00:00', 'datetime_llegada' => '2026-10-05 14:00:00',
        ...$transporte,
    ];
    $this->post = fn (array $transporte) => $this->actingAs($this->usuario, 'sanctum')
        ->postJson($this->url, ($this->tramo)($transporte));
});

it('un vehículo institucional se registra sin empresa', function () {
    $id = ($this->post)(['catalogo_transporte_id' => $this->institucional->id])
        ->assertCreated()
        ->assertJsonPath('datos.catalogo.codigo', 'vehiculo_institucional')
        ->json('datos.id');

    expect(TramoViatico::find($id))
        ->catalogo_transporte_id->toBe($this->institucional->id)
        ->empresa_transporte_id->toBeNull()
        ->and(AutorizacionVuelo::count())->toBe(0);
});

it('un tipo con empresas la sigue exigiendo', function () {
    ($this->post)(['catalogo_transporte_id' => $this->bus->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['empresa_transporte_id'], 'errores');

    expect(TramoViatico::count())->toBe(0);
});

it('la empresa tiene que ser del tipo elegido', function () {
    ($this->post)(['catalogo_transporte_id' => $this->bus->id, 'empresa_transporte_id' => $this->aerolinea->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['empresa_transporte_id'], 'errores');
});

it('sin tipo ni empresa no hay tramo', function () {
    ($this->post)([])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['catalogo_transporte_id'], 'errores');
});

it('con solo la empresa toma el tipo de ella, y un avión pide autorización', function () {
    $id = ($this->post)(['empresa_transporte_id' => $this->aerolinea->id])->assertCreated()->json('datos.id');

    expect(TramoViatico::find($id)->catalogo_transporte_id)->toBe($this->avion->id)
        ->and(AutorizacionVuelo::where('tramo_viatico_id', $id)->value('estado'))->toBe('pendiente');
});

it('al pasar un vuelo a vehículo institucional suelta la empresa y la autorización pendiente', function () {
    $id = ($this->post)(['catalogo_transporte_id' => $this->avion->id, 'empresa_transporte_id' => $this->aerolinea->id])
        ->assertCreated()->json('datos.id');
    expect(AutorizacionVuelo::count())->toBe(1);

    $this->actingAs($this->usuario, 'sanctum')
        ->putJson("{$this->url}/{$id}", ['catalogo_transporte_id' => $this->institucional->id])
        ->assertOk();

    expect(TramoViatico::find($id))
        ->catalogo_transporte_id->toBe($this->institucional->id)
        ->empresa_transporte_id->toBeNull()
        ->and(AutorizacionVuelo::count())->toBe(0);
});

it('los tipos de transporte dicen si tienen empresas', function () {
    $tipos = $this->actingAs($this->usuario, 'sanctum')
        ->getJson('/api/v1/viaticos/catalogos/tipos-transporte')
        ->assertOk()
        ->json('datos');

    expect(collect($tipos)->pluck('con_empresas', 'codigo')->all())->toBe([
        'bus' => true, 'avion' => true, 'vehiculo_institucional' => false,
    ]);
});

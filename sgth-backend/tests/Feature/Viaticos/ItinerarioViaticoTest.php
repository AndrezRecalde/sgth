<?php

/*
| El itinerario: tramos en orden, dentro de las fechas del viático, sin
| cruzarse, y completos para aprobar (2026-09-18).
|
| Antes un tramo podía salir antes que el viático o cruzarse con otro, borrar
| la ida dejaba el itinerario sin ella y con saltos en la numeración, y se
| aprobaba con un solo tramo de ida.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\CatalogoTransporte;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['codigo' => 'GITI']);
    $servidor = Servidor::create([
        'cedula' => '0800009101', 'nombre' => 'Ina', 'apellido' => 'Itinerario',
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $this->usuario = User::factory()->create(['servidor_id' => $servidor->id]);
    $this->usuario->assignRole('servidor');

    // Martes 6 a jueves 8 de octubre.
    $this->viatico = Viatico::create([
        'servidor_id' => $servidor->id, 'zona' => 'fuera_provincia',
        'datetime_salida' => '2026-10-06 08:00:00', 'datetime_llegada' => '2026-10-08 18:00:00',
        'noches' => 2, 'justificacion' => 'Comisión en Quito y Ambato',
        'estado' => EstadoViatico::SOLICITADO, 'monto_calculado' => 160,
        'monto_anticipo' => 0, 'modalidad_anticipo' => 'total',
    ]);

    // Cambiar las fechas del viático rehace el monto.
    DB::table('tarifas_viatico')->insert([
        'zona' => 'fuera_provincia', 'nivel' => 'servidor', 'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 80,
    ]);

    $this->institucional = CatalogoTransporte::create([
        'nombre' => 'Vehículo institucional', 'codigo' => 'vehiculo_institucional',
        'tipo_vehiculo' => 'terrestre', 'activo' => true,
    ]);

    $this->url = "/api/v1/viaticos/{$this->viatico->id}/tramos";

    /** Un tramo del viaje; el transporte, siempre el vehículo institucional. */
    $this->tramo = fn (string $de, string $a, string $sale, string $llega, array $extra = []) => [
        'origen_tipo' => 'nacional', 'origen_ciudad' => $de,
        'destino_tipo' => 'nacional', 'destino_ciudad' => $a,
        'catalogo_transporte_id' => $this->institucional->id,
        'datetime_salida' => $sale, 'datetime_llegada' => $llega,
        ...$extra,
    ];
    $this->post = fn (array $tramo) => $this->actingAs($this->usuario, 'sanctum')->postJson($this->url, $tramo);
    $this->numeracion = fn () => TramoViatico::where('viatico_id', $this->viatico->id)
        ->orderBy('orden')->get()->map(fn ($t) => "{$t->orden}:{$t->destino_ciudad}:{$t->tipo_tramo}")->all();
    $this->problemas = fn () => $this->actingAs($this->usuario, 'sanctum')
        ->getJson("/api/v1/viaticos/{$this->viatico->id}")->assertOk()->json('datos.itinerario_problemas');
});

it('un viaje a varios destinos queda numerado por fecha, con la ida primero', function () {
    // Se registran desordenados: el regreso antes que el tramo intermedio.
    ($this->post)(($this->tramo)('Esmeraldas', 'Quito', '2026-10-06 08:00', '2026-10-06 14:00'))->assertCreated();
    ($this->post)(($this->tramo)('Ambato', 'Esmeraldas', '2026-10-08 10:00', '2026-10-08 18:00', ['tipo_tramo' => 'regreso']))->assertCreated();
    ($this->post)(($this->tramo)('Quito', 'Ambato', '2026-10-07 09:00', '2026-10-07 12:00'))->assertCreated();

    expect(($this->numeracion)())->toBe(['1:Quito:ida', '2:Ambato:destino', '3:Esmeraldas:regreso'])
        ->and(($this->problemas)())->toBe([]);
});

it('un tramo no sale antes que el viático ni llega después de su regreso', function () {
    ($this->post)(($this->tramo)('Esmeraldas', 'Quito', '2026-10-05 20:00', '2026-10-06 04:00'))
        ->assertStatus(422)->assertJsonValidationErrors(['datetime_salida'], 'errores');

    ($this->post)(($this->tramo)('Quito', 'Esmeraldas', '2026-10-08 15:00', '2026-10-08 20:00'))
        ->assertStatus(422)->assertJsonValidationErrors(['datetime_llegada'], 'errores');
});

it('un tramo no se cruza con otro, y llega después de salir', function () {
    ($this->post)(($this->tramo)('Esmeraldas', 'Quito', '2026-10-06 08:00', '2026-10-06 14:00'))->assertCreated();

    ($this->post)(($this->tramo)('Quito', 'Ambato', '2026-10-06 12:00', '2026-10-06 16:00'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['datetime_salida'], 'errores')
        ->assertJsonPath('errores.datetime_salida.0', fn (string $m) => str_contains($m, 'Se cruza con el tramo 1'));

    ($this->post)(($this->tramo)('Quito', 'Ambato', '2026-10-07 12:00', '2026-10-07 11:00'))
        ->assertStatus(422)->assertJsonValidationErrors(['datetime_llegada'], 'errores');
});

it('el regreso es el último tramo si vuelve al lugar de salida; pasar por él a mitad del viaje es un destino', function () {
    // Esmeraldas → Quito → Esmeraldas → Atacames → Esmeraldas. Se escribe en
    // minúsculas a propósito: es el mismo lugar.
    ($this->post)(($this->tramo)('Esmeraldas', 'Quito', '2026-10-06 08:00', '2026-10-06 14:00'))->assertCreated();
    ($this->post)(($this->tramo)('Quito', 'esmeraldas', '2026-10-07 06:00', '2026-10-07 12:00'))->assertCreated();
    ($this->post)(($this->tramo)('Esmeraldas', 'Atacames', '2026-10-07 14:00', '2026-10-07 15:00'))->assertCreated();

    // Sin volver, el último no es regreso.
    expect(($this->numeracion)())->toBe(['1:Quito:ida', '2:esmeraldas:destino', '3:Atacames:destino']);

    ($this->post)(($this->tramo)('Atacames', 'Esmeraldas', '2026-10-08 16:00', '2026-10-08 18:00'))->assertCreated();

    expect(($this->numeracion)())->toBe(['1:Quito:ida', '2:esmeraldas:destino', '3:Atacames:destino', '4:Esmeraldas:regreso'])
        ->and(($this->problemas)())->toBe([]);
});

it('quien viaja solo dice si realiza actividades: la ida y el regreso los decide el sistema', function () {
    // Pide «regreso» en un tramo que no vuelve, e «ida» en uno que no es el primero.
    ($this->post)(($this->tramo)('Esmeraldas', 'Quito', '2026-10-06 08:00', '2026-10-06 14:00', ['tipo_tramo' => 'regreso']))->assertCreated();
    ($this->post)(($this->tramo)('Quito', 'Latacunga', '2026-10-07 08:00', '2026-10-07 10:00', ['tipo_tramo' => 'escala']))->assertCreated();
    ($this->post)(($this->tramo)('Latacunga', 'Ambato', '2026-10-07 11:00', '2026-10-07 12:00', ['tipo_tramo' => 'ida']))->assertCreated();

    expect(($this->numeracion)())->toBe(['1:Quito:ida', '2:Latacunga:escala', '3:Ambato:destino']);
});

it('con un destino en el exterior el viático tiene que ser de zona Exterior', function () {
    // Todas las noches se pagan como exterior si el viaje tiene un destino
    // fuera del país (Gestión Financiera, 2026-09-18). El viático está
    // «fuera de la provincia»: pagaría a tarifa nacional.
    ($this->post)(($this->tramo)('Esmeraldas', 'Quito', '2026-10-06 08:00', '2026-10-06 14:00'))->assertCreated();
    ($this->post)(($this->tramo)('Quito', 'Bogotá', '2026-10-07 08:00', '2026-10-07 10:00', [
        'destino_tipo' => 'internacional', 'destino_pais' => 'Colombia',
    ]))->assertCreated();
    ($this->post)(($this->tramo)('Bogotá', 'Esmeraldas', '2026-10-08 12:00', '2026-10-08 18:00', [
        'origen_tipo' => 'internacional', 'origen_pais' => 'Colombia',
    ]))->assertCreated();

    expect(($this->problemas)())
        ->toBe(['El itinerario incluye un destino en el exterior: el viático tiene que ser de zona Exterior.']);

    $this->viatico->update(['zona' => 'exterior']);

    expect(($this->problemas)())->toBe([]);
});

it('un viático al exterior necesita un tramo que llegue al exterior', function () {
    $this->viatico->update(['zona' => 'exterior']);
    ($this->post)(($this->tramo)('Esmeraldas', 'Quito', '2026-10-06 08:00', '2026-10-06 14:00'))->assertCreated();
    ($this->post)(($this->tramo)('Quito', 'Esmeraldas', '2026-10-08 12:00', '2026-10-08 18:00'))->assertCreated();

    expect(($this->problemas)())->toBe(['El viático es al exterior, pero ningún tramo llega al exterior.']);
});

it('al borrar la ida el siguiente tramo pasa a serlo, sin saltos en la numeración', function () {
    ($this->post)(($this->tramo)('Esmeraldas', 'Quito', '2026-10-06 08:00', '2026-10-06 14:00'))->assertCreated();
    ($this->post)(($this->tramo)('Quito', 'Ambato', '2026-10-07 09:00', '2026-10-07 12:00'))->assertCreated();
    $ida = TramoViatico::where('viatico_id', $this->viatico->id)->where('orden', 1)->sole();

    $this->actingAs($this->usuario, 'sanctum')->deleteJson("{$this->url}/{$ida->id}")->assertOk();

    expect(($this->numeracion)())->toBe(['1:Ambato:ida'])
        // Ahora el primer tramo no sale con el viático: se avisa.
        ->and(($this->problemas)())->toContain('El primer tramo sale el 07/10/2026 09:00; el viático, el 06/10/2026 08:00.');
});

it('un tramo se corrige sin borrarlo', function () {
    $id = ($this->post)(($this->tramo)('Esmeraldas', 'Quito', '2026-10-06 08:00', '2026-10-06 14:00'))->json('datos.id');

    $this->actingAs($this->usuario, 'sanctum')
        ->putJson("{$this->url}/{$id}", ['destino_ciudad' => 'Latacunga', 'datetime_llegada' => '2026-10-06 16:00'])
        ->assertOk()
        ->assertJsonPath('datos.destino_ciudad', 'Latacunga');

    expect(TramoViatico::find($id)->datetime_llegada->format('H:i'))->toBe('16:00');
});

it('sin regreso, o si cambian las fechas del viático, se avisa y no se aprueba', function () {
    ($this->post)(($this->tramo)('Esmeraldas', 'Quito', '2026-10-06 08:00', '2026-10-06 14:00'))->assertCreated();
    expect(($this->problemas)())->toBe(['Falta el tramo de regreso: el último tramo tiene que volver a Esmeraldas.']);

    ($this->post)(($this->tramo)('Quito', 'Esmeraldas', '2026-10-08 10:00', '2026-10-08 18:00', ['tipo_tramo' => 'regreso']))->assertCreated();
    expect(($this->problemas)())->toBe([]);

    // El viático cambia de fechas: el cambio se permite, pero los tramos ya
    // no coinciden y se avisa (decisión del usuario).
    $this->actingAs($this->usuario, 'sanctum')
        ->patchJson("/api/v1/viaticos/{$this->viatico->id}", ['datetime_llegada' => '2026-10-09 18:00'])
        ->assertOk();

    expect(($this->problemas)())->toBe(['El regreso llega el 08/10/2026 18:00; el viático regresa el 09/10/2026 18:00.']);

    $financiero = User::factory()->create();
    $financiero->assignRole('financiero');
    $this->actingAs($financiero, 'sanctum')
        ->postJson("/api/v1/viaticos/{$this->viatico->id}/aprobar")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'El regreso llega el 08/10/2026 18:00'));
});

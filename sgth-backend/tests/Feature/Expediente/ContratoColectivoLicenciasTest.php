<?php

namespace Tests\Feature\Expediente;

use App\Models\Asistencia\Licencia;
use App\Models\Expediente\ClausulaDeLicencia;
use App\Models\Expediente\ContratoColectivo;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 5.3 del diseño de Acciones de Personal (9.15): las cláusulas de licencia
| del contrato colectivo. La que amplía una causa de la ley tiene que mejorarla
| —los derechos del trabajador son irrenunciables—; la causa nueva lleva su
| nombre. La que ya respalda una licencia no se cambia ni se borra.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->contrato = ContratoColectivo::create([
        'numero' => 'CC-GADPE-2026', 'vigencia_desde' => '2026-01-01', 'vigencia_hasta' => '2027-12-31',
        'dictamen_finanzas_numero' => 'MEF-2026-0042', 'dictamen_finanzas_fecha' => '2026-01-20',
    ]);

    $this->ruta = "/api/v1/expediente/contratos-colectivos/{$this->contrato->id}/licencias";
    $this->guardar = fn (array $datos) => $this->postJson($this->ruta, ['clausula' => 'Cláusula 18', ...$datos]);
});

test('registra la que amplía una causa de la ley y la causa nueva, y el contrato las lista', function () {
    ($this->guardar)(['causa' => 'calamidad_domestica', 'alcance' => 'pariente_cercano', 'dias' => 10, 'dias_por_anio' => 15])
        ->assertCreated()
        ->assertJsonPath('datos.causa', 'calamidad_domestica')
        ->assertJsonPath('datos.nombre', null);

    ($this->guardar)(['clausula' => 'Cláusula 20', 'nombre' => 'Fallecimiento de parientes de tercer grado', 'dias' => 2])
        ->assertCreated()
        ->assertJsonPath('datos.causa', null)
        ->assertJsonPath('datos.dias', 2);

    $this->getJson('/api/v1/expediente/contratos-colectivos')
        ->assertOk()
        ->assertJsonCount(2, 'datos.contratos.0.clausulas_de_licencia')
        ->assertJsonPath('datos.vigente.clausulas_de_licencia.1.nombre', 'Fallecimiento de parientes de tercer grado');
});

test('la que amplía una causa tiene que mejorar la ley', function () {
    ($this->guardar)(['causa' => 'matrimonio', 'dias' => 3])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La ley ya da 3 días por Matrimonio o unión de hecho (LOSEP, Art. 27 j): una cláusula que da 3 no la mejora, y los derechos del trabajador son irrenunciables (Código del Trabajo, Art. 4).');

    // La calamidad distingue de quién es; la catastrófica no tiene tope.
    ($this->guardar)(['causa' => 'calamidad_domestica', 'dias' => 10])
        ->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'indique el caso que la cláusula amplía'));
    ($this->guardar)(['causa' => 'calamidad_domestica', 'alcance' => 'vecino', 'dias' => 10])
        ->assertStatus(422)->assertJsonPath('mensaje', 'Ese caso no corresponde a la licencia por Calamidad doméstica.');
    ($this->guardar)(['causa' => 'enfermedad_catastrofica', 'dias' => 200])
        ->assertStatus(422)->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'no tiene tope en la ley'));

    // Una vez por contrato y caso.
    ($this->guardar)(['causa' => 'matrimonio', 'dias' => 5])->assertCreated();
    ($this->guardar)(['clausula' => 'Cláusula 30', 'causa' => 'matrimonio', 'dias' => 6])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'El contrato ya amplía la licencia por Matrimonio o unión de hecho en la Cláusula 18: corrija esa.');
});

test('valida la forma: nombre o causa, y el tope por año no menor que una licencia', function () {
    ($this->guardar)(['dias' => 2])->assertStatus(422)->assertJsonValidationErrors(['nombre'], 'errores');
    ($this->guardar)(['causa' => 'matrimonio', 'nombre' => 'Boda', 'dias' => 5])
        ->assertStatus(422)->assertJsonValidationErrors(['nombre'], 'errores');
    ($this->guardar)(['causa' => 'pactada', 'dias' => 5])->assertStatus(422)->assertJsonValidationErrors(['causa'], 'errores');
    ($this->guardar)(['nombre' => 'Cumpleaños', 'dias' => 2, 'dias_por_anio' => 1])
        ->assertStatus(422)->assertJsonValidationErrors(['dias_por_anio'], 'errores');
});

test('solo el director de Talento Humano las carga', function () {
    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');
    $this->actingAs($asistente, 'sanctum');

    ($this->guardar)(['nombre' => 'Cumpleaños', 'dias' => 1])->assertForbidden();
});

test('la que ya respalda una licencia no se cambia ni se borra; la otra sí', function () {
    $usada = ClausulaDeLicencia::create([
        'contrato_colectivo_id' => $this->contrato->id, 'clausula' => 'Cláusula 21', 'nombre' => 'Cumpleaños', 'dias' => 1,
    ]);
    $libre = ClausulaDeLicencia::create([
        'contrato_colectivo_id' => $this->contrato->id, 'clausula' => 'Cláusula 22', 'nombre' => 'Grado de un hijo', 'dias' => 1,
    ]);

    Servidor::unguard();
    $obrero = Servidor::create(['cedula' => '0802704191', 'nombre' => 'Obrero', 'apellido' => 'Cumpleañero', 'regimen_laboral' => 'codigo_trabajo']);
    Licencia::create([
        'folio' => 'LIC-2026-00090', 'servidor_id' => $obrero->id, 'causa' => 'pactada', 'clausula_de_licencia_id' => $usada->id,
        'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-20', 'dias' => 1,
        'documento_ruta' => 'asistencia/licencias/x.pdf', 'documento_nombre' => 'x.pdf',
    ]);

    $this->putJson("{$this->ruta}/{$usada->id}", ['clausula' => 'Cláusula 21', 'nombre' => 'Cumpleaños', 'dias' => 2])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La Cláusula 21 ya respalda licencias registradas: no se cambia ni se borra. Si el contrato colectivo cambió, regístrelo como un contrato nuevo.');
    $this->deleteJson("{$this->ruta}/{$usada->id}")->assertStatus(422);

    $this->putJson("{$this->ruta}/{$libre->id}", ['clausula' => 'Cláusula 22', 'nombre' => 'Graduación de un hijo', 'dias' => 1])
        ->assertOk()
        ->assertJsonPath('datos.nombre', 'Graduación de un hijo');
    $this->deleteJson("{$this->ruta}/{$libre->id}")->assertOk();

    expect(ClausulaDeLicencia::pluck('id')->all())->toBe([$usada->id]);
});

test('la cláusula de otro contrato no se toca por la ruta de este', function () {
    $otro = ContratoColectivo::create(['numero' => 'CC-GADPE-2024', 'vigencia_desde' => '2024-01-01', 'vigencia_hasta' => '2025-12-31']);
    $ajena = ClausulaDeLicencia::create([
        'contrato_colectivo_id' => $otro->id, 'clausula' => 'Cláusula 9', 'nombre' => 'Día del trabajador municipal', 'dias' => 1,
    ]);

    $this->deleteJson("{$this->ruta}/{$ajena->id}")->assertNotFound();
    expect($ajena->fresh())->not->toBeNull();
});

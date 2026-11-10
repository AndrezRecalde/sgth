<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Enums\RolPuesto;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| El tipo de cada puesto (docs/diseno-manual-de-puestos.md, P9). El de
| plantilla es el del distributivo; el de contrato sostiene contratos que no
| ocupan plaza. Cada modalidad va en el suyo [TH D11, D12], salvo el reemplazo
| de un servidor ausente, que entra al puesto del titular [TH D13].
*/
beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    foreach ([Permiso::GESTIONAR_PUESTOS, Permiso::VER_ESTRUCTURA, Permiso::GESTIONAR_CONVOCATORIAS] as $permiso) {
        $rol->givePermissionTo(Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum']));
    }

    $this->user = User::factory()->create();
    $this->user->assignRole($rol);
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección del Tipo de Puesto']);
    $this->cargo = Cargo::create(['nombre' => 'Consultor de Proyectos']);
    $this->plantilla = puestoDePrueba($this->unidad, 'Analista de Plantilla', ['plazas' => 3]);
    $this->contrato = Puesto::create([
        'tipo' => 'contrato', 'plazas' => null, 'cargo_id' => $this->cargo->id,
        'unidad_administrativa_id' => $this->unidad->id, 'regimen_laboral' => 'losep',
    ]);

    $this->contratos = app(ContratoServidorService::class);
    $this->cedula = 1700000000;
    $this->vinculo = function (Puesto $puesto, TipoNombramiento $modalidad, array $extra = []): ContratoServidor {
        $servidor = Servidor::create([
            'cedula' => (string) ++$this->cedula, 'nombre' => 'Servidor', 'apellido' => 'De Prueba',
            'regimen_laboral' => 'losep',
        ]);

        return ContratoServidor::create($extra + [
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => $modalidad->value,
            'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $puesto->id,
            'fecha_inicio' => '2026-01-02', 'estado' => 'vigente',
            // Los contratos con plazo y el período de una autoridad lo exigen
            // (CHECK de la base).
            'fecha_fin' => $modalidad->exigePlazo() || $modalidad === TipoNombramiento::ELECCION_POPULAR ? '2026-12-31' : null,
        ]);
    };
});

test('un puesto de contrato no tiene plazas ni es jefatura; uno de plantilla tiene al menos una', function () {
    $nuevo = fn (array $datos) => $this->postJson('/api/v1/estructura/puestos', $datos + [
        'cargo_id' => $this->cargo->id, 'unidad_administrativa_id' => $this->unidad->id, 'regimen_laboral' => 'losep',
    ]);

    $creado = $nuevo(['tipo' => 'contrato'])->assertCreated();
    expect($creado->json('datos.tipo'))->toBe('contrato')
        ->and($creado->json('datos.plazas'))->toBeNull();

    $nuevo(['tipo' => 'contrato', 'plazas' => 2])->assertStatus(422)->assertJsonStructure(['errores' => ['plazas']]);
    $nuevo(['tipo' => 'contrato', 'es_jefe' => true])->assertStatus(422)->assertJsonStructure(['errores' => ['es_jefe']]);
    $nuevo(['tipo' => 'plantilla'])->assertStatus(422)->assertJsonStructure(['errores' => ['plazas']]);

    // Sin tipo, de plantilla, como nacían todos.
    expect($nuevo(['plazas' => 1])->assertCreated()->json('datos.tipo'))->toBe('plantilla');
});

test('cada modalidad va en el tipo de puesto que le corresponde', function () {
    $valida = fn (Puesto $p, TipoNombramiento $t) => fn () => $this->contratos->validarVacante($p->id, $t->value);

    expect($valida($this->contrato, TipoNombramiento::PERMANENTE))
        ->toThrow(ReglaNegocioException::class, 'es de contrato');
    expect($valida($this->contrato, TipoNombramiento::CODIGO_TRABAJO))->toThrow(ReglaNegocioException::class);
    expect($valida($this->plantilla, TipoNombramiento::SERVICIOS_PROFESIONALES))
        ->toThrow(ReglaNegocioException::class, 'es de plantilla');
    expect($valida($this->plantilla, TipoNombramiento::SERVICIOS_OCASIONALES))->toThrow(ReglaNegocioException::class);

    // Lo que sí corresponde pasa.
    $valida($this->plantilla, TipoNombramiento::PERMANENTE)();
    $valida($this->contrato, TipoNombramiento::SERVICIOS_OCASIONALES)();
    $valida($this->contrato, TipoNombramiento::SERVICIOS_PROFESIONALES)();
});

test('el reemplazo de un servidor ausente entra al puesto de plantilla del titular', function () {
    // El cuarto argumento es la ausencia que cubre: con ella, el ocasional cabe
    // en el puesto de plantilla y no consume su plaza.
    $this->contratos->validarVacante($this->plantilla->id, TipoNombramiento::SERVICIOS_OCASIONALES->value, null, 999);
    $this->contratos->validarVacante($this->plantilla->id, TipoNombramiento::SERVICIOS_PROFESIONALES->value, null, 999);

    expect(fn () => $this->contratos->validarVacante($this->contrato->id, TipoNombramiento::PERMANENTE->value, null, 999))
        ->toThrow(ReglaNegocioException::class);
});

test('la Plantilla cuenta solo los puestos de plantilla, y la plaza de la autoridad electa como ocupada', function () {
    ($this->vinculo)($this->plantilla, TipoNombramiento::PERMANENTE);
    ($this->vinculo)($this->contrato, TipoNombramiento::SERVICIOS_PROFESIONALES);
    ($this->vinculo)($this->contrato, TipoNombramiento::SERVICIOS_PROFESIONALES);

    $prefectura = puestoDePrueba($this->unidad, 'Prefecto', ['plazas' => 1, 'rol_puesto' => RolPuesto::DIGNATARIO->value]);
    ($this->vinculo)($prefectura, TipoNombramiento::ELECCION_POPULAR);

    $this->getJson('/api/v1/estructura/plantilla')
        ->assertOk()
        ->assertJsonPath('datos.plazas.total', 4)
        ->assertJsonPath('datos.plazas.ocupadas', 2)
        ->assertJsonPath('datos.plazas.vacantes', 2)
        ->assertJsonPath('datos.sin_plaza.servicios_profesionales', 2);

    // El listado de puestos dice lo mismo: la plaza del Prefecto no sale
    // «0 de 1 ocupadas» con la Prefecta en ella.
    $fila = collect($this->getJson('/api/v1/estructura/puestos?per_page=50')->json('datos'))
        ->firstWhere('id', $prefectura->id);
    expect($fila['plazas_ocupadas'])->toBe(1)
        ->and($fila['ocupantes'][0]['ocupa_plaza'])->toBeTrue()
        ->and($prefectura->fresh()->plazasLlenas())->toBe(1)
        // La validación de vacante sigue sin contarla: el día del relevo
        // coinciden la autoridad saliente y la entrante.
        ->and($prefectura->fresh()->plazasOcupadas())->toBe(0);
});

test('el tipo no cambia si lo que el puesto tiene no cabe en el nuevo', function () {
    ($this->vinculo)($this->plantilla, TipoNombramiento::PERMANENTE);

    $this->putJson("/api/v1/estructura/puestos/{$this->plantilla->id}", ['tipo' => 'contrato'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no pasa'));

    // Vacío, cambia, y las plazas quedan nulas sin enviarlas.
    $vacio = puestoDePrueba($this->unidad, 'Asistente', ['plazas' => 2]);
    $this->putJson("/api/v1/estructura/puestos/{$vacio->id}", ['tipo' => 'contrato'])->assertOk();
    expect($vacio->fresh()->plazas)->toBeNull();

    // Un profesional no deja pasar su puesto de contrato a plantilla.
    ($this->vinculo)($this->contrato, TipoNombramiento::SERVICIOS_PROFESIONALES);
    $this->putJson("/api/v1/estructura/puestos/{$this->contrato->id}", ['tipo' => 'plantilla', 'plazas' => 1])
        ->assertStatus(422);
});

test('el concurso formal va sobre un puesto de plantilla, y el express según su modalidad', function () {
    $convocatoria = fn (Puesto $p, array $datos) => $this->postJson('/api/v1/seleccion/convocatorias', $datos + [
        'puesto_id' => $p->id, 'titulo' => 'Convocatoria de prueba', 'descripcion' => 'Prueba',
        'tipo' => 'externa', 'vacantes' => 1,
    ]);
    $formal = ['tipo_proceso' => 'formal', 'fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-12-15'];
    $express = fn (TipoNombramiento $t) => ['tipo_proceso' => 'express', 'tipo_nombramiento_previsto' => $t->value];

    $convocatoria($this->contrato, $formal)->assertStatus(422)->assertJsonStructure(['errores' => ['puesto_id']]);
    $convocatoria($this->plantilla, $formal)->assertCreated();

    $convocatoria($this->plantilla, $express(TipoNombramiento::SERVICIOS_OCASIONALES))
        ->assertStatus(422)->assertJsonStructure(['errores' => ['puesto_id']]);
    $convocatoria($this->contrato, $express(TipoNombramiento::SERVICIOS_OCASIONALES))->assertCreated();
});

test('el listado filtra por tipo y dice las plazas ocupadas y la modalidad de cada ocupante', function () {
    ($this->vinculo)($this->plantilla, TipoNombramiento::PERMANENTE);
    ($this->vinculo)($this->contrato, TipoNombramiento::SERVICIOS_PROFESIONALES);

    $contratos = collect($this->getJson('/api/v1/estructura/puestos?tipo=contrato')->assertOk()->json('datos'));
    expect($contratos->pluck('id')->all())->toBe([$this->contrato->id]);
    expect($contratos->first()['ocupantes'][0])
        ->toMatchArray(['tipo_nombramiento' => 'servicios_profesionales', 'ocupa_plaza' => false]);

    $plantilla = collect($this->getJson('/api/v1/estructura/puestos?tipo=plantilla&per_page=50')->json('datos'))
        ->firstWhere('id', $this->plantilla->id);
    expect($plantilla['plazas_ocupadas'])->toBe(1)
        ->and($plantilla['ocupantes'][0]['ocupa_plaza'])->toBeTrue();
});

<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| «Por revisar» en Puestos y cargos (docs/diseno-manual-de-puestos.md, P9,
| PR 4). Todos los puestos nacieron de plantilla: el sistema propone cuáles
| parecen de contrato y qué ocasionales y profesionales están en uno de
| plantilla, y Talento Humano confirma cada caso.
*/
beforeEach(function () {
    $permisos = fn (array $nombres) => collect($nombres)->map(
        fn (Permiso $p) => Permission::firstOrCreate(['name' => $p->value, 'guard_name' => 'sanctum']),
    );

    $completo = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $completo->givePermissionTo($permisos([Permiso::VER_ESTRUCTURA, Permiso::GESTIONAR_PUESTOS, Permiso::GESTIONAR_EXPEDIENTE]));
    $this->talento = User::factory()->create();
    $this->talento->assignRole($completo);

    // Gestiona puestos pero no el expediente: ve la lista y no reubica.
    $soloPuestos = Role::firstOrCreate(['name' => 'solo-puestos-prueba', 'guard_name' => 'sanctum']);
    $soloPuestos->givePermissionTo($permisos([Permiso::VER_ESTRUCTURA, Permiso::GESTIONAR_PUESTOS]));
    $this->soloPuestos = User::factory()->create();
    $this->soloPuestos->assignRole($soloPuestos);

    $this->actingAs($this->talento, 'sanctum');

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de la Regularización']);
    $this->otraUnidad = unidadDePrueba(['nombre' => 'Otra Dirección', 'codigo' => 'OTRA-REG']);

    // Solo dos profesionales: parece de contrato, y sus 5 plazas salían vacantes.
    $this->consultor = puestoDePrueba($this->unidad, 'Consultor de Proyectos', ['plazas' => 5]);
    // Dos permanentes y un profesional: es de plantilla; el profesional, por reubicar.
    $this->analista = puestoDePrueba($this->unidad, 'Analista de TIC', ['plazas' => 3]);
    // El de contrato al que pasan, en la misma unidad.
    $this->deContrato = puestoDeContratoDePrueba($this->unidad, 'Consultor externo');

    $this->cedula = 1710000000;
    $this->vinculo = function (Puesto $puesto, TipoNombramiento $modalidad, array $extra = []): ContratoServidor {
        $servidor = Servidor::create([
            'cedula' => (string) ++$this->cedula, 'nombre' => 'Servidor', 'apellido' => 'Prueba '.$this->cedula,
            'regimen_laboral' => 'losep', 'puesto_id' => $puesto->id,
        ]);

        return ContratoServidor::create($extra + [
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => $modalidad->value,
            'unidad_administrativa_id' => $puesto->unidad_administrativa_id, 'puesto_id' => $puesto->id,
            'fecha_inicio' => '2026-01-02', 'estado' => 'vigente',
            'fecha_fin' => $modalidad->exigePlazo() ? '2026-12-31' : null,
        ]);
    };

    $this->profesionales = collect([
        ($this->vinculo)($this->consultor, TipoNombramiento::SERVICIOS_PROFESIONALES),
        ($this->vinculo)($this->consultor, TipoNombramiento::SERVICIOS_PROFESIONALES),
    ]);
    ($this->vinculo)($this->analista, TipoNombramiento::PERMANENTE);
    ($this->vinculo)($this->analista, TipoNombramiento::PERMANENTE);
    $this->profesionalDelAnalista = ($this->vinculo)($this->analista, TipoNombramiento::SERVICIOS_PROFESIONALES);

    $this->porRevisar = fn () => $this->getJson('/api/v1/estructura/tipo-de-puesto/por-revisar')->assertOk()->json('datos');
});

test('propone los puestos que parecen de contrato y los contratos en uno de plantilla', function () {
    // El reemplazo de un ausente entra al puesto del titular [TH D13]: no se propone.
    $titular = ($this->vinculo)(puestoDePrueba($this->unidad, 'Asistente', ['plazas' => 1]), TipoNombramiento::PERMANENTE);
    $ausencia = MovimientoPersonal::create([
        'servidor_id' => $titular->servidor_id, 'tipo_movimiento' => 'cambio_administrativo',
        'descripcion' => 'Comisión de servicios', 'fecha_efectiva' => '2026-02-01',
    ]);
    ($this->vinculo)(Puesto::findOrFail($titular->puesto_id), TipoNombramiento::SERVICIOS_OCASIONALES, ['cubre_movimiento_id' => $ausencia->id]);

    // Una jefatura no puede ser de contrato: tampoco se propone.
    ($this->vinculo)(puestoJefeDePrueba($this->unidad), TipoNombramiento::SERVICIOS_OCASIONALES);

    $datos = ($this->porRevisar)();

    expect(collect($datos['puestos'])->pluck('id')->all())->toBe([$this->consultor->id])
        ->and($datos['puestos'][0]['plazas'])->toBe(5)
        ->and(collect($datos['puestos'][0]['ocupantes'])->pluck('modalidad')->unique()->all())->toBe(['Servicios Profesionales']);

    $contratos = collect($datos['contratos']);
    // Los dos del consultor, el del analista y el ocasional de la jefatura; no el reemplazo.
    expect($contratos)->toHaveCount(4)
        ->and($contratos->pluck('id'))->toContain($this->profesionalDelAnalista->id)
        ->and($contratos->firstWhere('id', $this->profesionalDelAnalista->id)['destinos'])
        ->toBe([['id' => $this->deContrato->id, 'cargo' => 'Consultor externo']]);
});

test('pasar un puesto a contrato deja las plazas nulas, lo dice en la bitácora y lo saca de la lista', function () {
    $this->postJson("/api/v1/estructura/tipo-de-puesto/puestos/{$this->consultor->id}/pasar-a-contrato")
        ->assertOk()
        ->assertJsonPath('datos.tipo', 'contrato');

    $consultor = $this->consultor->fresh();
    expect($consultor->tipo->value)->toBe('contrato')
        ->and($consultor->plazas)->toBeNull();

    $registro = Activity::where('subject_type', Puesto::class)->where('subject_id', $consultor->id)->latest('id')->first();
    expect($registro->properties['causa'])->toBe('Regularización del tipo de puesto');

    $datos = ($this->porRevisar)();
    expect($datos['puestos'])->toBe([])
        // Sus profesionales ya están en un puesto de contrato.
        ->and(collect($datos['contratos'])->pluck('id')->all())->toBe([$this->profesionalDelAnalista->id]);

    // Con dos permanentes, el analista no pasa: lo impide la edición de siempre.
    $this->postJson("/api/v1/estructura/tipo-de-puesto/puestos/{$this->analista->id}/pasar-a-contrato")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no pasa'));
});

test('una jefatura no pasa a contrato', function () {
    $jefe = puestoJefeDePrueba($this->unidad);
    ($this->vinculo)($jefe, TipoNombramiento::SERVICIOS_OCASIONALES);

    $this->postJson("/api/v1/estructura/tipo-de-puesto/puestos/{$jefe->id}/pasar-a-contrato")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'jefatura'));
});

test('reubicar un profesional cambia su puesto, el del servidor y la bitácora, sin acción de personal', function () {
    $contrato = $this->profesionalDelAnalista;

    $this->postJson("/api/v1/estructura/tipo-de-puesto/contratos/{$contrato->id}/reubicar", [
        'puesto_destino_id' => $this->deContrato->id,
    ])->assertOk()->assertJsonPath('datos.puesto_id', $this->deContrato->id);

    expect($contrato->fresh()->puesto_id)->toBe($this->deContrato->id)
        // El puesto copiado en el servidor sigue al vínculo.
        ->and(Servidor::find($contrato->servidor_id)->puesto_id)->toBe($this->deContrato->id)
        ->and(MovimientoPersonal::where('servidor_id', $contrato->servidor_id)->exists())->toBeFalse();

    // El contrato ya tiene el «creado» de su observador; este es el de la reubicación.
    $registro = Activity::where('subject_type', ContratoServidor::class)->where('subject_id', $contrato->id)
        ->where('description', 'Contrato reubicado en un puesto de contrato')->sole();
    expect($registro->properties['antes'])->toBe(['puesto_id' => $this->analista->id])
        ->and($registro->properties['despues'])->toBe(['puesto_id' => $this->deContrato->id]);
});

test('no se reubica en otra unidad, en uno de plantilla, ni un reemplazo', function () {
    $reubicar = fn (ContratoServidor $c, Puesto $p) => $this->postJson(
        "/api/v1/estructura/tipo-de-puesto/contratos/{$c->id}/reubicar", ['puesto_destino_id' => $p->id],
    );

    // Cambiar de unidad es un traslado, con su acción de personal.
    $reubicar($this->profesionalDelAnalista, puestoDeContratoDePrueba($this->otraUnidad, 'Consultor lejano'))
        ->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'otra unidad'));

    $reubicar($this->profesionalDelAnalista, $this->consultor)
        ->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'puesto de contrato'));

    $this->profesionalDelAnalista->update(['cubre_movimiento_id' => MovimientoPersonal::create([
        'servidor_id' => $this->profesionales[0]->servidor_id, 'tipo_movimiento' => 'cambio_administrativo',
        'descripcion' => 'Licencia', 'fecha_efectiva' => '2026-02-01',
    ])->id]);
    $reubicar($this->profesionalDelAnalista, $this->deContrato)
        ->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'reemplaza'));

    expect($this->profesionalDelAnalista->fresh()->puesto_id)->toBe($this->analista->id);
});

test('el ocasional solo pasa a un puesto de contrato de su mismo grupo ocupacional', function () {
    $ocasional = ($this->vinculo)($this->analista, TipoNombramiento::SERVICIOS_OCASIONALES);
    $otroGrupo = GrupoOcupacional::create([
        'grado_codigo' => 'SP3-REG', 'grado_numerico' => 3, 'grupo' => 'Servidor Público 3',
        'rmu' => 901, 'regimen' => 'losep', 'activo' => true,
    ]);
    $mismoGrupo = puestoDeContratoDePrueba($this->unidad, 'Analista ocasional', [
        'grupo_ocupacional_id' => $this->analista->grupo_ocupacional_id,
    ]);
    $this->deContrato->update(['grupo_ocupacional_id' => $otroGrupo->id]);

    $fila = collect(($this->porRevisar)()['contratos'])->firstWhere('id', $ocasional->id);
    expect(collect($fila['destinos'])->pluck('id')->all())->toBe([$mismoGrupo->id]);

    // Otro grupo sería otro grado: eso es una acción de personal.
    $this->postJson("/api/v1/estructura/tipo-de-puesto/contratos/{$ocasional->id}/reubicar", [
        'puesto_destino_id' => $this->deContrato->id,
    ])->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'grupo ocupacional'));

    $this->postJson("/api/v1/estructura/tipo-de-puesto/contratos/{$ocasional->id}/reubicar", [
        'puesto_destino_id' => $mismoGrupo->id,
    ])->assertOk();
});

test('ver la lista pide gestionar puestos, y reubicar, además, el expediente', function () {
    $this->actingAs($this->soloPuestos, 'sanctum');
    $this->getJson('/api/v1/estructura/tipo-de-puesto/por-revisar')->assertOk();
    $this->postJson("/api/v1/estructura/tipo-de-puesto/contratos/{$this->profesionalDelAnalista->id}/reubicar", [
        'puesto_destino_id' => $this->deContrato->id,
    ])->assertForbidden();

    $this->actingAs(User::factory()->create(), 'sanctum');
    $this->getJson('/api/v1/estructura/tipo-de-puesto/por-revisar')->assertForbidden();
    $this->postJson("/api/v1/estructura/tipo-de-puesto/puestos/{$this->consultor->id}/pasar-a-contrato")->assertForbidden();
});

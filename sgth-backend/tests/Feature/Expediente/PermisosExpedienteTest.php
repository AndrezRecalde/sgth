<?php

namespace Tests\Feature\Expediente;

use App\Models\Catalogo\EntidadFinanciera;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\DocumentoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| Huecos de la auditoría del Expediente del 2026-09-19: rutas que no pedían
| rol ni revisaban quién llamaba, y una ficha que su titular podía reescribir
| entera. Cada prueba entra como un servidor sin ningún rol de Talento Humano.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['servidor', 'admin-uath', 'asistente-uath'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }

    $unidad = unidadDePrueba(['codigo' => 'UATH', 'nombre' => 'Talento Humano']);
    $puesto = puestoDePrueba($unidad);

    $this->propio = Servidor::create([
        'cedula' => '1111111111', 'nombre' => 'Titular', 'apellido' => 'Propio',
        'regimen_laboral' => 'losep', 'estado' => true,
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'fecha_ingreso_institucion' => '2020-01-01',
    ]);
    $this->ajeno = Servidor::create([
        'cedula' => '2222222222', 'nombre' => 'Titular', 'apellido' => 'Ajeno',
        'regimen_laboral' => 'losep', 'estado' => true,
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
    ]);

    $this->servidor = User::factory()->create(['servidor_id' => $this->propio->id]);
    $this->servidor->assignRole('servidor');

    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');

    $this->entidad = EntidadFinanciera::create(['nombre' => 'Banco de prueba', 'tipo' => 'banco']);
});

function datosCuenta(int $entidadId): array
{
    return [
        'entidad_financiera_id' => $entidadId,
        'numero_cuenta'         => '2200112233',
        'tipo_cuenta'           => 'ahorros',
        'proposito'             => 'sueldo',
        'es_principal_sueldo'   => true,
    ];
}

test('un servidor no puede ver ni tocar las cuentas bancarias de otro', function () {
    $this->actingAs($this->servidor, 'sanctum');
    $base = "/api/v1/expediente/servidores/{$this->ajeno->id}/cuentas-bancarias";

    $this->getJson($base)->assertForbidden();
    $this->postJson($base, datosCuenta($this->entidad->id))->assertForbidden();

    expect($this->ajeno->cuentasBancarias()->count())->toBe(0);
});

test('un servidor tampoco cambia su propia cuenta de sueldo desde el expediente', function () {
    $this->actingAs($this->servidor, 'sanctum');

    $this->postJson(
        "/api/v1/expediente/servidores/{$this->propio->id}/cuentas-bancarias",
        datosCuenta($this->entidad->id),
    )->assertForbidden();
});

test('talento humano sigue registrando cuentas bancarias', function () {
    $this->actingAs($this->uath, 'sanctum');

    $this->postJson(
        "/api/v1/expediente/servidores/{$this->ajeno->id}/cuentas-bancarias",
        datosCuenta($this->entidad->id),
    )->assertCreated();
});

test('un servidor no puede descargar los documentos de otro', function () {
    Storage::fake('local');
    Storage::put('expedientes/2222222222/cedula.pdf', 'contenido');

    $documento = DocumentoServidor::create([
        'servidor_id' => $this->ajeno->id, 'tipo_documento' => 'cedula_identidad',
        'nombre_archivo' => 'cedula.pdf', 'ruta_archivo' => 'expedientes/2222222222/cedula.pdf',
        'tamanio_bytes' => 9, 'mime_type' => 'application/pdf', 'estado' => true,
        'subido_por' => $this->uath->id,
    ]);

    $url = "/api/v1/expediente/servidores/{$this->ajeno->id}/documentos/{$documento->id}/descargar";

    $this->actingAs($this->servidor, 'sanctum');
    $this->getJson($url)->assertForbidden();

    $this->actingAs($this->uath, 'sanctum');
    $this->get($url)->assertOk();
});

test('un servidor no puede registrar condiciones en la carga familiar de otro', function () {
    $carga = CargaFamiliar::create([
        'servidor_id' => $this->ajeno->id, 'cedula' => '3333333333',
        'nombres' => 'Hija', 'apellidos' => 'Ajena', 'parentesco' => 'hijo',
        'fecha_nacimiento' => '2015-01-01',
    ]);

    $this->actingAs($this->servidor, 'sanctum');

    $this->postJson("/api/v1/expediente/cargas-familiares/{$carga->id}/discapacidades", [
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 40,
    ])->assertForbidden();

    $this->postJson("/api/v1/expediente/cargas-familiares/{$carga->id}/enfermedades", [
        'tipo_enfermedad' => 'Insuficiencia renal',
    ])->assertForbidden();
});

test('un tipo de discapacidad fuera del catálogo es un error de validación, no un 500', function () {
    $carga = CargaFamiliar::create([
        'servidor_id' => $this->ajeno->id, 'cedula' => '3333333333',
        'nombres' => 'Hija', 'apellidos' => 'Ajena', 'parentesco' => 'hijo',
        'fecha_nacimiento' => '2015-01-01',
    ]);

    $this->actingAs($this->uath, 'sanctum');

    $this->postJson("/api/v1/expediente/cargas-familiares/{$carga->id}/discapacidades", [
        'tipo_discapacidad' => 'inventada', 'porcentaje' => 40,
    ])->assertUnprocessable()->assertJsonValidationErrors('tipo_discapacidad', 'errores');
});

test('el titular solo cambia su contacto; cédula, régimen y fechas quedan para talento humano', function () {
    $this->actingAs($this->servidor, 'sanctum');
    $url = "/api/v1/expediente/servidores/{$this->propio->id}";

    $this->putJson($url, [
        'cedula'                    => '3333333333',
        'regimen_laboral'           => 'codigo_trabajo',
        'fecha_ingreso_institucion' => '1990-01-01',
        'tiene_discapacidad'        => true,
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'cedula', 'regimen_laboral', 'fecha_ingreso_institucion', 'tiene_discapacidad',
    ], 'errores');

    $ficha = $this->propio->fresh();
    expect($ficha->cedula)->toBe('1111111111')
        ->and($ficha->regimen_laboral->value)->toBe('losep')
        ->and($ficha->fecha_ingreso_institucion->toDateString())->toBe('2020-01-01');

    $this->putJson($url, [
        'telefono_celular' => '0999999999',
        'correo_personal'  => 'titular@example.com',
    ])->assertOk();

    expect($this->propio->fresh()->telefono_celular)->toBe('0999999999');
});

test('talento humano sigue editando toda la ficha', function () {
    $this->actingAs($this->uath, 'sanctum');

    $this->putJson("/api/v1/expediente/servidores/{$this->propio->id}", [
        'cedula' => '3333333333', 'regimen_laboral' => 'codigo_trabajo',
    ])->assertOk();

    expect($this->propio->fresh()->cedula)->toBe('3333333333');
});

test('una cédula repetida se explica en español, no como validation.unique', function () {
    $this->actingAs($this->uath, 'sanctum');

    $this->putJson("/api/v1/expediente/servidores/{$this->propio->id}", [
        'cedula' => $this->ajeno->cedula,
    ])->assertUnprocessable()
        ->assertJsonPath('errores.cedula.0', 'Esta cédula ya está registrada en otro expediente.');
});

test('el filtro de inactivos lista a los que no están en funciones', function () {
    // El propio está en funciones: activo y con vínculo vigente.
    ContratoServidor::create([
        'servidor_id' => $this->propio->id, 'tipo_nombramiento' => 'nombramiento_permanente',
        'fecha_inicio' => '2020-01-01', 'estado' => 'vigente',
        'puesto_id' => $this->propio->puesto_id,
        'unidad_administrativa_id' => $this->propio->unidad_administrativa_id,
    ]);
    // Y hay uno que ya salió.
    $this->ajeno->update(['estado' => false]);

    $this->actingAs($this->uath, 'sanctum');

    $inactivos = $this->getJson('/api/v1/expediente/servidores?en_funciones=false')
        ->assertOk()->json('datos');
    expect(collect($inactivos)->pluck('id')->all())->toBe([$this->ajeno->id]);

    $enFunciones = $this->getJson('/api/v1/expediente/servidores?en_funciones=true')
        ->assertOk()->json('datos');
    expect(collect($enFunciones)->pluck('id')->all())->toBe([$this->propio->id]);
});

test('el listado no entrega más de 500 fichas por página', function () {
    $this->actingAs($this->uath, 'sanctum');

    $this->getJson('/api/v1/expediente/servidores?per_page=100000')
        ->assertOk()->assertJsonPath('meta.por_pagina', 500);
});

test('no existe una ruta para borrar fichas', function () {
    $this->actingAs($this->uath, 'sanctum');

    $this->deleteJson("/api/v1/expediente/servidores/{$this->ajeno->id}")
        ->assertStatus(405);
    expect(Servidor::find($this->ajeno->id))->not->toBeNull();
});

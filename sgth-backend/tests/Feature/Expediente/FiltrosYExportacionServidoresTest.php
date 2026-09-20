<?php

namespace Tests\Feature\Expediente;

use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ExpedienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| Nivel 4 de la auditoría del Expediente (2026-09-19): los filtros del listado
| y la exportación de nómina.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');
    $this->actingAs($this->uath, 'sanctum');

    $this->direccion = unidadDePrueba(['codigo' => 'GA', 'nombre' => 'Gestión Administrativa', 'nivel' => 2]);
    $this->jefatura = UnidadAdministrativa::create([
        'codigo' => 'GA-TH', 'nombre' => 'Talento Humano', 'nivel' => 3,
        'unidad_padre_id' => $this->direccion->id,
    ]);
    $this->otra = unidadDePrueba(['codigo' => 'GF', 'nombre' => 'Gestión Financiera', 'nivel' => 2]);

    $this->puestoDireccion = puestoDePrueba($this->direccion);
    $this->puestoJefatura = puestoDePrueba($this->jefatura, 'Asistente');
});

function servidorDePrueba(array $atributos): Servidor
{
    return Servidor::create(array_merge([
        'regimen_laboral' => 'losep',
        'estado' => true,
    ], $atributos));
}

test('el buscador encuentra por nombre completo, en cualquier orden', function () {
    servidorDePrueba([
        'cedula' => '1111111111', 'nombre' => 'Juan', 'segundo_nombre' => 'Carlos',
        'apellido' => 'Pérez', 'segundo_apellido' => 'Mina',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);
    servidorDePrueba([
        'cedula' => '2222222222', 'nombre' => 'Ana', 'apellido' => 'Quiñónez',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);

    $buscar = fn (string $texto) => collect(
        $this->getJson('/api/v1/expediente/servidores?search='.urlencode($texto))
            ->assertOk()->json('datos')
    )->pluck('cedula')->all();

    expect($buscar('Juan Pérez'))->toBe(['1111111111'])
        ->and($buscar('Pérez Juan'))->toBe(['1111111111'])
        ->and($buscar('juan carlos'))->toBe(['1111111111'])
        ->and($buscar('1111'))->toBe(['1111111111'])
        ->and($buscar('Juan Quiñónez'))->toBe([]);
});

test('filtrar por una dirección incluye a los servidores de sus jefaturas', function () {
    servidorDePrueba([
        'cedula' => '1111111111', 'nombre' => 'De la', 'apellido' => 'Dirección',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);
    servidorDePrueba([
        'cedula' => '2222222222', 'nombre' => 'De la', 'apellido' => 'Jefatura',
        'puesto_id' => $this->puestoJefatura->id,
        'unidad_administrativa_id' => $this->jefatura->id,
    ]);
    servidorDePrueba([
        'cedula' => '3333333333', 'nombre' => 'De', 'apellido' => 'Financiera',
        'puesto_id' => puestoDePrueba($this->otra, 'Contador')->id,
        'unidad_administrativa_id' => $this->otra->id,
    ]);

    $cedulas = collect(
        $this->getJson("/api/v1/expediente/servidores?unidad_administrativa_id={$this->direccion->id}")
            ->assertOk()->json('datos')
    )->pluck('cedula')->sort()->values()->all();

    expect($cedulas)->toBe(['1111111111', '2222222222']);
});

test('el estado del contrato mira el vínculo actual, no los anteriores', function () {
    $conVinculo = servidorDePrueba([
        'cedula' => '1111111111', 'nombre' => 'Sigue', 'apellido' => 'Trabajando',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);
    // Tuvo un contrato que terminó y hoy tiene uno vigente.
    ContratoServidor::create([
        'servidor_id' => $conVinculo->id, 'tipo_nombramiento' => 'servicios_ocasionales',
        'fecha_inicio' => '2023-01-01', 'fecha_fin' => '2023-12-31', 'estado' => 'terminado',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);
    ContratoServidor::create([
        'servidor_id' => $conVinculo->id, 'tipo_nombramiento' => 'nombramiento_permanente',
        'fecha_inicio' => '2024-01-01', 'estado' => 'vigente',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);

    $exservidor = servidorDePrueba([
        'cedula' => '2222222222', 'nombre' => 'Ya', 'apellido' => 'Salió', 'estado' => false,
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);
    ContratoServidor::create([
        'servidor_id' => $exservidor->id, 'tipo_nombramiento' => 'servicios_ocasionales',
        'fecha_inicio' => '2024-01-01', 'fecha_fin' => '2024-06-30', 'estado' => 'terminado',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);

    $porEstado = fn (string $estado) => collect(
        $this->getJson("/api/v1/expediente/servidores?contrato_estado={$estado}")
            ->assertOk()->json('datos')
    )->pluck('cedula')->all();

    expect($porEstado('terminado'))->toBe(['2222222222'])
        ->and($porEstado('vigente'))->toBe(['1111111111']);
});

test('la exportación trae la fecha de salida del último vínculo cerrado', function () {
    $exservidor = servidorDePrueba([
        'cedula' => '2222222222', 'nombre' => 'Ya', 'apellido' => 'Salió', 'estado' => false,
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
        'fecha_ingreso_institucion' => '2022-01-03',
    ]);
    ContratoServidor::create([
        'servidor_id' => $exservidor->id, 'tipo_nombramiento' => 'servicios_ocasionales',
        'fecha_inicio' => '2022-01-03', 'fecha_fin' => '2022-12-31', 'estado' => 'terminado',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);
    ContratoServidor::create([
        'servidor_id' => $exservidor->id, 'tipo_nombramiento' => 'servicios_ocasionales',
        'fecha_inicio' => '2023-02-01', 'fecha_fin' => '2024-06-30', 'estado' => 'terminado',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);

    $fila = app(ExpedienteService::class)->exportarServidores([])->firstWhere('CÉDULA', '2222222222');

    expect($fila['FECHA DE SALIDA'])->toBe('2024-06-30');
});

test('quien está en funciones no muestra fecha de salida', function () {
    $activo = servidorDePrueba([
        'cedula' => '1111111111', 'nombre' => 'Sigue', 'apellido' => 'Trabajando',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);
    ContratoServidor::create([
        'servidor_id' => $activo->id, 'tipo_nombramiento' => 'nombramiento_permanente',
        'fecha_inicio' => '2024-01-01', 'estado' => 'vigente',
        'puesto_id' => $this->puestoDireccion->id,
        'unidad_administrativa_id' => $this->direccion->id,
    ]);

    $fila = app(ExpedienteService::class)->exportarServidores([])->firstWhere('CÉDULA', '1111111111');

    expect($fila['FECHA DE SALIDA'])->toBe('EN FUNCIONES');
});

<?php

use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * La partida que el Expediente enseña de un vínculo es la del contrato.
 *
 * Hasta el 2026-10-03 la pestaña Laboral y la «Situación actual» leían la del
 * puesto: un ocasional sobre un puesto de plantilla aparecía con la partida de
 * nombramiento, y no cuadraba con la `partida_origen` del PDF de la acción.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');

    $nombramiento = PartidaPresupuestaria::create([
        'codigo' => '510105', 'descripcion' => 'Remuneraciones Unificadas',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);
    $this->ocasionales = PartidaPresupuestaria::create([
        'codigo' => '510510', 'descripcion' => 'Servicios Personales por Contrato',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $unidad = unidadDePrueba(['nombre' => 'Unidad Partidas']);
    $puesto = puestoDePrueba($unidad, 'Puesto de plantilla');
    $puesto->update(['partida_presupuestaria_id' => $nombramiento->id]);

    $this->servidor = Servidor::forceCreate([
        'cedula' => '0804444441', 'nombre' => 'Olga', 'apellido' => 'Ocasional',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);

    $this->contrato = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'servicios_ocasionales',
        'unidad_administrativa_id' => $unidad->id,
        'puesto_id' => $puesto->id,
        'partida_presupuestaria_id' => $this->ocasionales->id,
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-12-31',
        'estado' => 'vigente',
    ]);

    $this->actingAs($this->uath, 'sanctum');
});

test('la actividad laboral trae la partida del contrato', function () {
    $this->getJson("/api/v1/expediente/servidores/{$this->servidor->id}/actividad-laboral")
        ->assertOk()
        ->assertJsonPath('datos.0.contrato.partida_presupuestaria.codigo', '510510')
        // La del puesto sigue llegando: es el respaldo de los vínculos viejos.
        ->assertJsonPath('datos.0.contrato.puesto.partida_presupuestaria.codigo', '510105');
});

test('la ficha trae la partida del contrato vigente', function () {
    $this->getJson("/api/v1/expediente/servidores/{$this->servidor->id}")
        ->assertOk()
        ->assertJsonPath('datos.contrato_vigente.partida_presupuestaria.codigo', '510510');
});
